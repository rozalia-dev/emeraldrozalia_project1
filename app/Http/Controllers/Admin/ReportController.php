<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRecord;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Permission;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\ReportAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const SECTIONS = ['overview', 'approvals', 'custom', 'history', 'returns', 'roles', 'scheduler'];
    public const SECTION_PATTERN = 'overview|approvals|custom|history|returns|roles|scheduler';

    private const ORDER_TYPES = [
        'online' => 'Online Orders',
        'corporate' => 'Corporate Orders',
        'bulk' => 'Bulk Orders',
        'franchise' => 'Franchise Orders',
        'franchise_retail' => 'Franchise Retail Orders',
        'buyer' => 'Buyer Orders',
    ];

    private const REPORT_MODULES = [
        'Order Reports',
        'Franchise Reports',
        'Retail Store Reports',
        'Product & Sales Reports',
        'Customer Reports',
        'Communication Reports',
    ];

    private const REPORT_TYPES = [
        'Tabular Report',
        'Summary Report',
        'Matrix Report',
        'Chart Report',
        'KPI Report',
        'Comparison Report',
        'Geographic Report',
        'Funnel Report',
    ];

    private const REPORT_FIELDS = [
        'Order Number',
        'Order Type',
        'Order Status',
        'Payment Status',
        'Order Date',
        'Customer Name',
        'Customer Email',
        'Order Value (EUR)',
        'Items Ordered',
        'Return Status',
    ];

    public function overview(Request $request): View
    {
        $filters = $this->filters($request);
        $orders = $this->orders($filters);
        $runCount = $this->applyReportFilters(AdminRecord::query()->where('module', 'report-runs'), $filters)->count();
        $scheduled = AdminRecord::query()->where('module', 'report-schedules')->where('status', 'active')->count();
        $customCount = $this->applyReportFilters(AdminRecord::query()->where('module', 'custom-reports')->where('status', 'active'), $filters)->count();

        return view('admin.reports.index', [
            'section' => 'overview',
            'title' => 'General Reporting / Report Center',
            'subtitle' => 'Central hub to access, build, schedule and manage all reports across the system.',
            'filters' => $filters,
            ...$this->overviewData($orders, $filters, $runCount, $scheduled, $customCount),
        ]);
    }

    public function page(Request $request, string $section): View
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);
        if ($section === 'overview') {
            return $this->overview($request);
        }

        $filters = $this->filters($request);
        $payload = match ($section) {
            'approvals' => $this->approvalData($filters),
            'custom' => $this->customData(),
            'history' => $this->historyData($request),
            'returns' => $this->returnsData($filters),
            'roles' => $this->rolesData($request),
            'scheduler' => $this->schedulerData($request),
        };

        return view('admin.reports.index', [
            'section' => $section,
            'title' => $this->titles()[$section]['title'],
            'subtitle' => $this->titles()[$section]['subtitle'],
            'filters' => $filters,
            ...$payload,
        ]);
    }

    public function order(Request $request, ReportAnalyticsService $analytics): View
    {
        return view('admin.reports.analytics', $analytics->build('order', $request));
    }

    public function communication(Request $request, ReportAnalyticsService $analytics): View
    {
        return view('admin.reports.analytics', $analytics->build('communication', $request));
    }

    public function customer(Request $request, ReportAnalyticsService $analytics): View
    {
        return view('admin.reports.analytics', $analytics->build('customer', $request));
    }

    public function run(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'report' => ['required', 'string', 'max:180'],
            'module' => ['nullable', 'string', 'max:120'],
        ]);
        $startedAt = microtime(true);
        $module = trim((string) ($data['module'] ?? '')) ?: 'General Reporting';
        $records = $this->reportRecordCount($module);
        $durationSeconds = round(microtime(true) - $startedAt, 4);
        $run = AdminRecord::create([
            'module' => 'report-runs',
            'reference' => 'run-'.Str::lower(Str::random(10)),
            'title' => $data['report'],
            'status' => 'success',
            'record_date' => now(),
            'user_id' => auth()->id(),
            'data' => [
                'module' => $module,
                'records' => $records,
                'duration_seconds' => $durationSeconds,
                'duration' => $this->durationLabel($durationSeconds),
                'format' => null,
                'delivery' => 'Manual run',
                'source' => 'Live database query',
            ],
        ]);
        AuditTrail::record('reports.run', $run, null, $run->toArray());

        return back()->with('success', 'Report “'.$data['report'].'” ran successfully and was added to history.');
    }

    public function export(Request $request, ReportAnalyticsService $analytics): StreamedResponse
    {
        $format = (string) $request->query('format', 'csv');
        $name = Str::slug((string) $request->query('name', 'report-center'));
        $analyticsReport = (string) $request->query('analytics', '');
        $rows = in_array($analyticsReport, ReportAnalyticsService::REPORTS, true)
            ? $analytics->exportRows($analyticsReport, $request)
            : $this->exportRows($request);
        $filename = 'emerald-rozalia-'.$name.'-'.now()->format('Ymd-His').'.'.($format === 'json' ? 'json' : 'csv');

        if ($format === 'json') {
            return response()->streamDownload(static function () use ($rows): void {
                echo json_encode([
                    'exported_at' => now()->toIso8601String(),
                    'application' => 'Emerald Rozalia Project 1',
                    'rows' => $rows,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }, $filename, ['Content-Type' => 'application/json']);
        }

        return response()->streamDownload(static function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Report', 'Module', 'Status', 'Records', 'Generated']);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function storeCustom(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:500'],
            'report_type' => ['required', 'string', 'max:80'],
            'module' => ['required', 'string', 'max:120'],
            'data_source' => ['required', 'string', 'max:120'],
            'group_by' => ['nullable', 'string', 'max:120'],
            'selected_modules' => ['nullable', 'array'],
            'selected_modules.*' => ['string', 'max:120'],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['string', 'max:120'],
            'filters' => ['nullable', 'array'],
            'filters.*.field' => ['nullable', 'string', 'max:120'],
            'filters.*.condition' => ['nullable', 'string', 'max:40'],
            'filters.*.value' => ['nullable', 'string', 'max:180'],
            'filters.*.logic' => ['nullable', 'string', 'max:10'],
            'sorts' => ['nullable', 'array'],
            'sorts.*.field' => ['nullable', 'string', 'max:120'],
            'sorts.*.direction' => ['nullable', 'string', 'max:10'],
            'calculated_fields' => ['nullable', 'array'],
            'calculated_fields.*' => ['nullable', 'string', 'max:180'],
            'share_with' => ['nullable', 'string', 'max:120'],
            'favorite' => ['nullable', 'boolean'],
            'make_public' => ['nullable', 'boolean'],
            'schedule' => ['nullable', 'boolean'],
        ]);
        $record = AdminRecord::create([
            'module' => 'custom-reports',
            'reference' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
            'title' => $data['name'],
            'status' => 'active',
            'record_date' => now(),
            'user_id' => auth()->id(),
            'data' => Arr::except($data, ['name', 'schedule']),
        ]);
        AuditTrail::record('reports.custom.created', $record, null, $record->toArray());

        if ($request->boolean('schedule')) {
            $ownerEmail = (string) auth()->user()?->email;
            if (! filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['schedule' => 'A valid account email is required before a custom report can be scheduled.']);
            }
            $this->createSchedule($data['name'], [
                'report' => $data['name'],
                'frequency' => 'weekly',
                'day' => now()->format('l'),
                'time' => now()->format('H:i'),
                'recipients' => $ownerEmail,
                'format' => 'CSV',
                'active' => true,
            ]);
        }

        return redirect()->route('admin.reports.custom')->with('success', 'Custom report saved with a UUID and audit entry.');
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'report' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:500'],
            'frequency' => ['required', 'string', 'max:40'],
            'day' => ['nullable'],
            'time' => ['required', 'date_format:H:i'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'timezone' => ['nullable', 'string', 'max:80'],
            'orientation' => ['nullable', 'string', 'in:landscape,portrait'],
            'include_charts' => ['nullable', 'boolean'],
            'password_protect' => ['nullable', 'boolean'],
            'delivery_methods' => ['nullable', 'array'],
            'delivery_methods.*' => ['string', 'max:40'],
            'recipients' => ['required', 'string', 'max:1000'],
            'recipient_extra' => ['nullable', 'array'],
            'recipient_extra.*' => ['nullable', 'string', 'max:255'],
            'email_subject' => ['nullable', 'string', 'max:180'],
            'email_message' => ['nullable', 'string', 'max:2000'],
            'reply_to' => ['nullable', 'email', 'max:180'],
            'run_as' => ['nullable', 'string', 'max:40'],
            'data_refresh' => ['nullable', 'string', 'max:40'],
            'include_drilldown' => ['nullable', 'boolean'],
            'max_records' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'save_mode' => ['nullable', 'in:draft'],
            'format' => ['required', 'string', 'in:PDF,Excel,CSV'],
            'active' => ['nullable', 'boolean'],
        ]);
        $extraRecipients = array_values(array_filter($data['recipient_extra'] ?? [], static fn ($value): bool => trim((string) $value) !== ''));
        $data['recipients'] = $this->normaliseRecipients((string) $data['recipients'], $extraRecipients);
        if (is_array($data['day'] ?? null)) {
            $data['day'] = implode(', ', $data['day']);
        }
        if (($data['save_mode'] ?? null) === 'draft') {
            $data['active'] = false;
        }
        $schedule = $this->createSchedule($data['name'], $data);
        AuditTrail::record('reports.schedule.created', $schedule, null, $schedule->toArray());

        return redirect()->route('admin.reports.scheduler')->with('success', 'Report schedule saved and activated.');
    }

    public function toggleSchedule(AdminRecord $schedule): RedirectResponse
    {
        abort_unless($schedule->module === 'report-schedules', 404);
        $updated = DB::transaction(function () use ($schedule): AdminRecord {
            $locked = AdminRecord::query()->where('module', 'report-schedules')->lockForUpdate()->findOrFail($schedule->getKey());
            $before = $locked->toArray();
            $locked->update(['status' => $locked->status === 'active' ? 'paused' : 'active']);
            AuditTrail::record('reports.schedule.toggled', $locked, $before, $locked->fresh()->toArray());

            return $locked;
        });

        return back()->with('success', 'Schedule status updated to '.$updated->status.'.');
    }

    public function duplicateSchedule(AdminRecord $schedule): RedirectResponse
    {
        abort_unless($schedule->module === 'report-schedules', 404);
        $copy = $this->createSchedule($schedule->title.' Copy', array_merge((array) $schedule->data, [
            'source_uuid' => $schedule->public_uuid,
            'active' => false,
        ]));
        AuditTrail::record('reports.schedule.duplicated', $copy, null, $copy->toArray());

        return back()->with('success', 'Schedule duplicated in paused state for review.');
    }

    public function deleteSchedule(AdminRecord $schedule): RedirectResponse
    {
        abort_unless($schedule->module === 'report-schedules', 404);
        DB::transaction(function () use ($schedule): void {
            $locked = AdminRecord::query()->where('module', 'report-schedules')->lockForUpdate()->findOrFail($schedule->getKey());
            $before = $locked->toArray();
            $locked->update(['status' => 'deleted']);
            AuditTrail::record('reports.schedule.deleted', $locked, $before, $locked->fresh()->toArray());
        });

        return back()->with('success', 'Schedule archived from the report calendar.');
    }

    public function pruneHistory(): RedirectResponse
    {
        $runs = DB::transaction(function () {
            $runs = AdminRecord::query()
                ->where('module', 'report-runs')
                ->where('record_date', '<', now()->subDays(90)->toDateString())
                ->where('status', '!=', 'deleted')
                ->lockForUpdate()
                ->get();

            foreach ($runs as $run) {
                $before = $run->toArray();
                $run->update(['status' => 'deleted']);
                AuditTrail::record('reports.history.pruned', $run, $before, $run->fresh()->toArray());
            }

            return $runs;
        });

        return back()->with('success', $runs->count().' report runs older than 90 days were archived.');
    }

    private function createSchedule(string $title, array $data): AdminRecord
    {
        $payload = Arr::except($data, ['name', 'active']);
        $payload['report'] ??= $title;
        $payload['frequency'] ??= 'weekly';
        $payload['format'] ??= 'CSV';
        if (isset($payload['recipients'])) {
            $payload['recipients'] = $this->normaliseRecipients((string) $payload['recipients']);
        }

        return AdminRecord::create([
            'module' => 'report-schedules',
            'reference' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'title' => $title,
            'status' => ($data['active'] ?? true) ? 'active' : 'paused',
            'record_date' => now(),
            'user_id' => auth()->id(),
            'data' => $payload,
        ]);
    }

    private function filters(Request $request): array
    {
        $today = now();
        $from = $this->filterDate($request->query('from'), $today->copy()->startOfMonth())->startOfDay();
        $to = $this->filterDate($request->query('to'), $today)->endOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'company' => (string) $request->query('company', 'All Companies'),
            'compare' => (string) $request->query('compare', 'previous_period'),
            'view' => (string) $request->query('view', 'summary'),
            'module' => (string) $request->query('module', 'all'),
            'report_type' => (string) $request->query('report_type', 'all'),
            'created_by' => (string) $request->query('created_by', 'all'),
            'tag' => (string) $request->query('tag', 'all'),
            'status' => (string) $request->query('status', 'all'),
            'favorite' => (string) $request->query('favorite', 'all'),
            'from' => $from,
            'to' => $to,
            'from_label' => $from->format('d M Y'),
            'to_label' => $to->format('d M Y'),
        ];
    }

    private function filterDate(mixed $value, Carbon $fallback): Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $fallback->copy();
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return $fallback->copy();
        }

        return $date->format('Y-m-d') === $value ? $date : $fallback->copy();
    }

    private function orders(array $filters): Collection
    {
        return Order::query()
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['q'], fn ($query, $term) => $query->where(fn ($inner) => $inner
                ->where('number', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')))
            ->latest('created_at')
            ->get();
    }

    private function applyReportFilters($query, array $filters)
    {
        return $query
            ->whereBetween('record_date', [$filters['from']->toDateString(), $filters['to']->toDateString()])
            ->when(($filters['q'] ?? '') !== '', fn ($builder) => $builder->where('title', 'like', '%'.$filters['q'].'%'))
            ->when(($filters['module'] ?? 'all') !== 'all', fn ($builder) => $builder->where('data->module', $filters['module']))
            ->when(($filters['report_type'] ?? 'all') !== 'all', fn ($builder) => $builder->where('data->report_type', $filters['report_type']))
            ->when(($filters['status'] ?? 'all') !== 'all', fn ($builder) => $builder->where('status', $filters['status']))
            ->when(is_numeric($filters['created_by'] ?? null), fn ($builder) => $builder->where('user_id', (int) $filters['created_by']))
            ->when(($filters['tag'] ?? 'all') !== 'all', fn ($builder) => $builder->where('data->tag', $filters['tag']))
            ->when(($filters['favorite'] ?? 'all') === '1', fn ($builder) => $builder->where('data->favorite', true));
    }

    private function overviewData(Collection $orders, array $filters, int $runCount, int $scheduled, int $customCount): array
    {
        $records = AdminRecord::query()
            ->whereIn('module', ['report-runs', 'custom-reports'])
            ->where('status', '!=', 'deleted')
            ->whereBetween('record_date', [$filters['from']->toDateString(), $filters['to']->toDateString()])
            ->latest('created_at')
            ->get();
        $runs = $records->where('module', 'report-runs')->values();
        $custom = $records->where('module', 'custom-reports')->values();
        $sourceRows = $custom
            ->groupBy(fn (AdminRecord $record): string => (string) (data_get($record->data, 'data_source') ?: 'Not recorded'))
            ->map(fn (Collection $rows, string $label): array => ['label' => $label, 'value' => $rows->count(), 'share' => $this->share($rows->count(), $custom->count()), 'color' => '#2676cc'])
            ->sortByDesc('value')->values()->all();
        $moduleRows = $records
            ->groupBy(fn (AdminRecord $record): string => (string) (data_get($record->data, 'module') ?: 'General Reporting'))
            ->map(fn (Collection $rows, string $label): array => ['label' => $label, 'count' => $rows->count(), 'tone' => 'blue'])
            ->sortByDesc('count')->values();
        $favorites = $custom->filter(fn (AdminRecord $record): bool => (bool) data_get($record->data, 'favorite'))->map(fn (AdminRecord $record): array => [
            'title' => $record->title,
            'module' => data_get($record->data, 'module') ?: 'Not recorded',
        ])->values()->take(8)->all();
        $recent = $runs->take(8)->map(fn (AdminRecord $record): array => [
            'title' => $record->title,
            'module' => data_get($record->data, 'module') ?: 'Not recorded',
            'by' => $record->user?->name ?: ($record->user_id ? 'User #'.$record->user_id : 'System'),
            'date' => $record->created_at?->format('d M Y, H:i'),
            'records' => data_get($record->data, 'records'),
        ])->values()->all();
        $alerts = [];
        $failedRuns = $records->where('module', 'report-runs')->whereIn('status', ['failed', 'error'])->count();
        $pausedSchedules = AdminRecord::query()->where('module', 'report-schedules')->where('status', 'paused')->count();
        if ($failedRuns > 0) {
            $alerts[] = ['label' => number_format($failedRuns).' report runs failed', 'tone' => 'red', 'date' => now()->format('d M Y, H:i')];
        }
        if ($pausedSchedules > 0) {
            $alerts[] = ['label' => number_format($pausedSchedules).' report schedules are paused', 'tone' => 'orange', 'date' => now()->format('d M Y, H:i')];
        }
        $activeUsers = $records->pluck('user_id')->filter()->unique()->count();
        $exportCount = $runs->filter(fn (AdminRecord $record): bool => str_contains(strtolower((string) data_get($record->data, 'delivery')), 'download'))->count();
        $totalReports = $runCount + $customCount;

        return [
            'hasReportData' => $records->isNotEmpty() || $orders->isNotEmpty(),
            'dataNote' => $records->isEmpty() && $orders->isEmpty()
                ? 'No report, order or schedule records exist for the selected period.'
                : 'Live database data · '.$records->count().' persisted report records and '.$orders->count().' orders in the selected period',
            'metrics' => [
                $this->metric('Total Reports', number_format($totalReports), null, 'green', 'file-text'),
                $this->metric('Reports Run', number_format($runCount), null, 'blue', 'play'),
                $this->metric('Scheduled Reports', number_format($scheduled), null, 'purple', 'calendar'),
                $this->metric('Exports', number_format($exportCount), null, 'orange', 'download'),
                $this->metric('Active Users', number_format($activeUsers), null, 'teal', 'users'),
                $this->metric('Favorite Reports', number_format(count($favorites)), null, 'red', 'star'),
                $this->metric('Reports Shared', number_format($custom->filter(fn (AdminRecord $record): bool => filled(data_get($record->data, 'share_with')) || (bool) data_get($record->data, 'make_public'))->count()), null, 'gold', 'shield'),
                $this->metric('Data Sources', number_format($custom->pluck('data')->map(fn ($data) => data_get($data, 'data_source'))->filter()->unique()->count()), null, 'blue', 'database'),
            ],
            'summary' => [
                'total' => $orders->count(),
                'approved' => $orders->where('payment_status', 'paid')->count(),
                'revenue' => (float) $orders->where('payment_status', 'paid')->sum('total'),
                'custom' => $customCount,
            ],
            'moduleRows' => $moduleRows,
            'moduleOptions' => self::REPORT_MODULES,
            'runs' => $runs->take(8),
            'schedules' => AdminRecord::query()->where('module', 'report-schedules')->where('status', '!=', 'deleted')->latest('created_at')->take(8)->get(),
            'recent' => $recent,
            'favorites' => $favorites,
            'alerts' => $alerts,
            'sourceRows' => $sourceRows,
            'filters' => $filters,
        ];
    }

    private function approvalData(array $filters): array
    {
        $approvals = Approval::query()
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->with(['requestedBy', 'approver', 'decidedBy'])
            ->latest('created_at')
            ->get();
        $counts = $approvals->countBy('status');
        $total = $approvals->count();
        $approved = (int) $counts->get('approved', 0);
        $pending = (int) ($counts->get('pending', 0) + $counts->get('in-progress', 0));
        $rejected = (int) $counts->get('rejected', 0);
        $hold = (int) $counts->get('escalated', 0);
        $decided = $approvals->filter(fn (Approval $approval): bool => $approval->decided_at !== null);
        $durations = $decided->map(fn (Approval $approval): float => max(0, $approval->created_at?->diffInSeconds($approval->decided_at) ?? 0) / 86400)->filter(fn (float $value): bool => $value > 0)->values();
        $slaRows = $approvals->filter(fn (Approval $approval): bool => $approval->due_at !== null);
        $slaWithin = $slaRows->filter(function (Approval $approval): bool {
            $end = $approval->decided_at ?: now();
            return $end->lessThanOrEqualTo($approval->due_at);
        })->count();
        $tatStats = [
            'overall' => $durations->isNotEmpty() ? number_format((float) $durations->avg(), 2).' Days' : '—',
            'fastest' => $durations->isNotEmpty() ? number_format((float) $durations->min(), 2).' Days' : '—',
            'slowest' => $durations->isNotEmpty() ? number_format((float) $durations->max(), 2).' Days' : '—',
            'within_sla' => $slaRows->isNotEmpty() ? number_format(($slaWithin / $slaRows->count()) * 100, 2).'%' : '—',
            'breaches' => $slaRows->count() > 0 ? $slaRows->count() - $slaWithin : 0,
        ];
        $moduleRows = $approvals->groupBy(fn (Approval $approval): string => Str::headline((string) ($approval->request_type ?: $approval->source ?: $approval->entity_type ?: 'Not recorded')))
            ->map(fn (Collection $rows, string $label): array => $this->approvalAggregate($label, $rows))->sortByDesc('requests')->values()->all();
        $approvers = $approvals->groupBy(fn (Approval $approval): string => (string) ($approval->approver_id ?: $approval->decided_by ?: 'unassigned'))
            ->map(function (Collection $rows): array {
                $first = $rows->first();
                $person = $first?->approver ?: $first?->decidedBy;
                return $this->approvalAggregate($person?->name ?: 'Unassigned', $rows, $person?->name ? null : 'Not recorded');
            })->sortByDesc('requests')->values()->take(8)->all();
        $pendingRows = $approvals->filter(fn (Approval $approval): bool => in_array($approval->status, ['pending', 'in-progress', 'escalated'], true))->take(8)->map(fn (Approval $approval): array => [
            'request' => $approval->title,
            'reference' => $approval->reference,
            'module' => Str::headline((string) ($approval->request_type ?: $approval->source ?: 'Not recorded')),
            'by' => $approval->requester_name ?: $approval->requestedBy?->name ?: ($approval->requested_by ? 'User #'.$approval->requested_by : 'Not recorded'),
            'age' => $approval->created_at?->diffForHumans(),
            'priority' => Str::headline((string) ($approval->priority ?: 'normal')),
        ])->values()->all();
        $activity = $approvals->take(8)->map(fn (Approval $approval): array => [
            'request' => $approval->title,
            'reference' => $approval->reference,
            'status' => Str::headline((string) $approval->status),
            'by' => $approval->decidedBy?->name ?: $approval->approver?->name ?: $approval->requester_name ?: 'Not recorded',
            'time' => $approval->created_at?->diffForHumans(),
        ])->values()->all();
        $requestTypes = $approvals->groupBy(fn (Approval $approval): string => Str::headline((string) ($approval->request_type ?: 'Not recorded')))
            ->map(fn (Collection $rows, string $label): array => ['label' => $label, 'value' => $rows->count(), 'percent' => $this->share($rows->count(), $total), 'color' => '#2676cc'])
            ->sortByDesc('value')->values()->all();
        $statusRows = $counts->sortDesc()->map(fn (int $value, string $status): array => [
            'label' => Str::headline($status),
            'status' => $status,
            'value' => $value,
            'percent' => $this->share($value, $total),
            'tone' => $this->statusTone($status),
        ])->values()->all();

        return [
            'hasApprovalData' => $approvals->isNotEmpty(),
            'dataNote' => $approvals->isEmpty() ? 'No approval requests found for the selected period.' : 'Live database data · '.$total.' approval requests in the selected period',
            'metrics' => [
                $this->metric('Total Approval Requests', number_format($total), null, 'green', 'check'),
                $this->metric('Approved', number_format($approved), null, 'blue', 'check'),
                $this->metric('Pending', number_format($pending), null, 'orange', 'clock'),
                $this->metric('Rejected', number_format($rejected), null, 'red', 'close'),
                $this->metric('On Hold', number_format($hold), null, 'purple', 'pause'),
                $this->metric('Avg. Turnaround Time', $tatStats['overall'], null, 'green', 'clock'),
            ],
            'approvalStats' => compact('total', 'approved', 'pending', 'rejected', 'hold'),
            'statusRows' => $statusRows,
            'moduleRows' => $moduleRows,
            'approvers' => $approvers,
            'pendingRows' => $pendingRows,
            'activity' => $activity,
            'requestTypes' => $requestTypes,
            'approvalTrend' => $this->bucketRows($approvals, $filters['from'], $filters['to'], fn (Collection $rows): int => $rows->count()),
            'tatStats' => $tatStats,
            'filters' => $filters,
        ];
    }

    private function customData(): array
    {
        $reports = AdminRecord::query()
            ->where('module', 'custom-reports')
            ->where('status', 'active')
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();
        $templates = $reports->getCollection()->filter(fn (AdminRecord $record): bool => (bool) data_get($record->data, 'template'))->map(fn (AdminRecord $record): array => [
            'name' => $record->title,
            'type' => data_get($record->data, 'report_type') ?: 'Not recorded',
            'uuid' => $record->public_uuid,
        ])->values()->all();

        return [
            'hasCustomData' => $reports->total() > 0,
            'dataNote' => $reports->total() > 0 ? 'Live database data · '.$reports->total().' saved custom reports' : 'No saved custom reports exist yet.',
            'customReports' => $reports,
            'reportTypes' => self::REPORT_TYPES,
            'modules' => array_values(array_unique(array_merge(self::REPORT_MODULES, array_values(self::ORDER_TYPES)))),
            'fields' => self::REPORT_FIELDS,
            'filterFields' => ['Order Status', 'Order Type', 'Order Value (EUR)', 'Payment Status', 'Order Date'],
            'templates' => $templates,
        ];
    }

    private function historyData(Request $request): array
    {
        $query = AdminRecord::query()
            ->where('module', 'report-runs')
            ->where('status', '!=', 'deleted')
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('module') && $request->query('module') !== 'all', fn ($q) => $q->where('data->module', (string) $request->query('module')))
            ->when($request->filled('status') && $request->query('status') !== 'all', fn ($q) => $q->where('status', (string) $request->query('status')))
            ->when($request->filled('user_id') && is_numeric($request->query('user_id')), fn ($q) => $q->where('user_id', (int) $request->query('user_id')))
            ->when($request->filled('method') && $request->query('method') !== 'all', fn ($q) => $q->where('data->delivery', 'like', '%'.(string) $request->query('method').'%'));
        $allRuns = (clone $query)->latest('created_at')->get();
        $runs = (clone $query)->latest('created_at')->paginate(10)->withQueryString();
        $selectedId = (int) $request->query('selected', 0);
        $selectedRun = $selectedId ? AdminRecord::query()->where('module', 'report-runs')->where('status', '!=', 'deleted')->find($selectedId) : $runs->first();
        $statusCounts = $allRuns->countBy('status')->sortDesc();
        $durations = $allRuns->pluck('data')->map(fn ($data): mixed => data_get($data, 'duration_seconds'))->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value);
        $topReports = $allRuns->groupBy('title')->map(fn (Collection $rows, string $title): array => ['title' => $title, 'count' => $rows->count()])->sortByDesc('count')->values()->take(8)->all();
        $statusBreakdown = $statusCounts->map(fn (int $value, string $status): array => [
            'label' => Str::headline($status),
            'value' => $value,
            'percent' => $this->share($value, $allRuns->count()),
            'tone' => $this->statusTone($status),
        ])->values()->all();

        return [
            'hasHistoryData' => $allRuns->isNotEmpty(),
            'dataNote' => $allRuns->isEmpty() ? 'No report runs match the selected filters.' : 'Live database data · '.$allRuns->count().' report runs match the selected filters',
            'history' => $runs,
            'selectedRun' => $selectedRun,
            'topReports' => $topReports,
            'historyMetrics' => [
                $this->metric('Total Runs', number_format($allRuns->count()), null, 'green', 'file-text'),
                $this->metric('Successful Runs', number_format($allRuns->where('status', 'success')->count()), null, 'blue', 'check'),
                $this->metric('Failed Runs', number_format($allRuns->whereIn('status', ['failed', 'error'])->count()), null, 'orange', 'alert'),
                $this->metric('In Progress', number_format($allRuns->whereIn('status', ['in_progress', 'in-progress'])->count()), null, 'purple', 'clock'),
                $this->metric('Downloads', number_format($allRuns->filter(fn (AdminRecord $run): bool => str_contains(strtolower((string) data_get($run->data, 'delivery')), 'download'))->count()), null, 'teal', 'download'),
                $this->metric('Avg. Run Time', $durations->isNotEmpty() ? $this->durationLabel((float) $durations->avg()) : '—', null, 'gold', 'clock'),
            ],
            'statusBreakdown' => $statusBreakdown,
            'moduleOptions' => self::REPORT_MODULES,
            'filters' => $this->filters($request),
        ];
    }

    private function returnsData(array $filters): array
    {
        $returns = ReturnRequest::query()
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->with(['order.items', 'order.user'])
            ->latest('created_at')
            ->get();
        $orders = Order::query()->whereBetween('created_at', [$filters['from'], $filters['to']])->get();
        $total = $returns->count();
        $returnOrderCount = $returns->pluck('order_id')->filter()->unique()->count();
        $refundReturns = $returns->filter(fn (ReturnRequest $return): bool => $return->status === 'refunded' || $return->type === 'refund');
        $refundOrders = $refundReturns->map(fn (ReturnRequest $return): ?Order => $return->order)->filter()->unique('id')->values();
        $refunds = (float) $refundOrders->sum(fn (Order $order): float => (float) $order->total);
        $processing = $returns->filter(fn (ReturnRequest $return): bool => in_array($return->status, ['approved', 'received', 'refunded', 'rejected'], true) && $return->updated_at && $return->created_at)
            ->map(fn (ReturnRequest $return): float => max(0, $return->created_at->diffInSeconds($return->updated_at)) / 86400)
            ->filter(fn (float $days): bool => $days > 0);
        $statusCounts = $returns->countBy('status')->sortDesc();
        $status = $statusCounts->map(fn (int $value, string $status): array => [
            'label' => Str::headline($status),
            'value' => $value,
            'percent' => $this->share($value, $total).'%',
            'tone' => $this->statusTone($status),
        ])->values()->all();
        $reasonRows = $returns->groupBy(fn (ReturnRequest $return): string => Str::headline((string) ($return->reason ?: 'Not recorded')))
            ->map(fn (Collection $rows, string $label): array => ['label' => $label, 'value' => $rows->count(), 'percent' => $this->share($rows->count(), $total), 'bar_percent' => $this->barPercent($rows->count(), $statusCounts->max() ?: 1)])
            ->sortByDesc('value')->values()->take(10)->all();
        $moduleRows = $returns->groupBy(fn (ReturnRequest $return): string => (string) ($return->order?->order_type ?: 'not_recorded'))
            ->map(function (Collection $rows, string $type) use ($orders): array {
                $orderCount = $orders->where('order_type', $type)->count();
                $linked = $rows->filter(fn (ReturnRequest $return): bool => $return->order !== null)->map(fn (ReturnRequest $return): ?Order => $return->order)->filter()->unique('id');
                $refundRows = $rows->filter(fn (ReturnRequest $return): bool => $return->status === 'refunded' || $return->type === 'refund')->map(fn (ReturnRequest $return): ?Order => $return->order)->filter()->unique('id');
                $duration = $rows->filter(fn (ReturnRequest $return): bool => $return->updated_at && $return->created_at)->map(fn (ReturnRequest $return): float => max(0, $return->created_at->diffInSeconds($return->updated_at)) / 86400)->filter(fn (float $days): bool => $days > 0);
                return [
                    'label' => self::ORDER_TYPES[$type] ?? Str::headline($type),
                    'returns' => $rows->count(),
                    'rate' => $orderCount > 0 ? number_format(($rows->count() / $orderCount) * 100, 2).'%' : '—',
                    'refunds' => $this->moneyLabel((float) $refundRows->sum(fn (Order $order): float => (float) $order->total), $this->currencyFor($refundRows)),
                    'time' => $duration->isNotEmpty() ? number_format((float) $duration->avg(), 2).' Days' : '—',
                    'linked_orders' => $linked->count(),
                ];
            })->sortByDesc('returns')->values()->all();
        $productRows = $this->returnProductRows($returns);
        $paymentRows = $refundOrders->groupBy(fn (Order $order): string => Str::headline((string) ($order->payment_method ?: 'Not recorded')))
            ->map(fn (Collection $rows, string $label): array => ['label' => $label, 'value' => $this->moneyLabel((float) $rows->sum('total'), $this->currencyFor($rows)), 'percent' => $this->share($rows->sum('total'), $refunds).'%', 'orders' => $rows->count()])
            ->sortByDesc('orders')->values()->all();
        $currency = $this->currencyFor($refundOrders);

        return [
            'hasReturnData' => $returns->isNotEmpty(),
            'dataNote' => $returns->isEmpty() ? 'No return requests found for the selected period.' : 'Live database data · '.$total.' return requests in the selected period',
            'returnMetrics' => [
                $this->metric('Total Returns', number_format($total), null, 'green', 'refresh'),
                $this->metric('Total Refunds', $this->moneyLabel($refunds, $currency), null, 'blue', 'credit-card'),
                $this->metric('Return Orders', number_format($returnOrderCount), null, 'orange', 'shopping-bag'),
                $this->metric('Refund Orders', number_format($refundOrders->count()), null, 'purple', 'file-text'),
                $this->metric('Return Rate', $orders->count() ? number_format(($returnOrderCount / $orders->count()) * 100, 2).'%' : '—', null, 'red', 'percent'),
                $this->metric('Avg. Processing Time', $processing->isNotEmpty() ? number_format((float) $processing->avg(), 2).' Days' : '—', null, 'teal', 'clock'),
            ],
            'returnStatus' => $status,
            'reasonRows' => $reasonRows,
            'moduleRows' => $moduleRows,
            'productRows' => $productRows,
            'paymentRows' => $paymentRows,
            'recentReturns' => $returns->take(8),
            'returnTrend' => $this->bucketRows($returns, $filters['from'], $filters['to'], fn (Collection $rows): float => (float) $rows->count()),
            'refundTrend' => $this->bucketRows($returns, $filters['from'], $filters['to'], function (Collection $rows): float {
                return (float) $rows->filter(fn (ReturnRequest $return): bool => $return->status === 'refunded' || $return->type === 'refund')
                    ->map(fn (ReturnRequest $return): float => (float) ($return->order?->total ?: 0))->sum();
            }),
            'filters' => $filters,
        ];
    }

    private function rolesData(Request $request): array
    {
        $rolesQuery = Role::query()
            ->withCount(['users', 'permissions'])
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->string('q').'%'))
            ->orderBy('name');
        $roles = (clone $rolesQuery)->paginate(12)->withQueryString();
        $selectedId = (int) $request->query('selected', 0);
        $selectedRole = $selectedId ? Role::query()->with(['permissions', 'users'])->find($selectedId) : $roles->first();
        $permissions = $selectedRole?->permissions ?: collect();
        $permissionRows = $this->permissionRows($permissions);
        $totalUsers = User::query()->count();
        $activeUsers = User::query()->where('status', 'active')->whereNull('locked_at')->count();
        $roleChanges = $selectedRole ? AuditLog::query()->where('subject_type', Role::class)->where('subject_id', $selectedRole->id)->latest('created_at')->take(8)->get()->map(fn (AuditLog $log): array => [
            'date' => $log->created_at?->format('d M Y, H:i'),
            'role' => $selectedRole->name,
            'by' => $log->user?->name ?: ($log->user_id ? 'User #'.$log->user_id : 'System'),
            'type' => Str::headline(Str::afterLast((string) $log->action, '.')),
        ])->all() : [];
        $userByRole = Role::query()->withCount('users')->orderByDesc('users_count')->orderBy('name')->get()->filter(fn (Role $role): bool => $role->users_count > 0)->map(fn (Role $role): array => [
            'label' => $role->name,
            'value' => $role->users_count,
        ])->values()->all();
        $roleMetrics = [
            $this->metric('Total Roles', number_format($rolesQuery->count()), null, 'green', 'users'),
            $this->metric('Total Users', number_format($totalUsers), null, 'blue', 'user'),
            $this->metric('Active Users', number_format($activeUsers), null, 'green', 'check'),
            $this->metric('Disabled Users', number_format(max(0, $totalUsers - $activeUsers)), null, 'red', 'user'),
            $this->metric('Permissions', number_format(Permission::query()->count()), null, 'purple', 'key'),
            $this->metric('System Access', 'RBAC enforced', null, 'gold', 'shield'),
        ];

        return [
            'hasRoleData' => $roles->total() > 0,
            'dataNote' => $roles->total() > 0 ? 'Live database data · '.$roles->total().' roles' : 'No roles have been configured yet.',
            'roles' => $roles,
            'roleMetrics' => $roleMetrics,
            'selectedRole' => $selectedRole,
            'selectedRoleId' => $selectedRole?->id,
            'roleSummary' => $selectedRole ? [
                'name' => $selectedRole->name,
                'level' => $selectedRole->level !== null ? (string) $selectedRole->level : 'Not recorded',
                'users' => (int) ($selectedRole->users_count ?? $selectedRole->users->count()),
                'permissions' => $permissions->count(),
                'status' => $selectedRole->is_active ? 'Active' : 'Inactive',
                'created_by' => $selectedRole->created_by ? 'User #'.$selectedRole->created_by : 'Not recorded',
                'last_updated' => $selectedRole->updated_at?->format('d M Y, H:i'),
                'description' => $selectedRole->description ?: 'Not recorded',
            ] : null,
            'moduleAccess' => $permissionRows,
            'permissionRows' => $permissionRows,
            'permissionOverview' => $permissions->count(),
            'userByRole' => $userByRole,
            'roleChanges' => $roleChanges,
        ];
    }

    private function schedulerData(Request $request): array
    {
        $schedules = AdminRecord::query()->where('module', 'report-schedules')->where('status', '!=', 'deleted')->latest('created_at')->paginate(8)->withQueryString();
        $selectedId = (int) $request->query('selected', 0);
        $selectedSchedule = $selectedId ? AdminRecord::query()->where('module', 'report-schedules')->where('status', '!=', 'deleted')->find($selectedId) : $schedules->first();
        $runs = AdminRecord::query()->where('module', 'report-runs')->where('status', '!=', 'deleted')->latest('created_at')->take(5)->get();
        $deliveryHistory = $runs->map(fn (AdminRecord $run): array => [
            'date' => $run->created_at?->format('d M Y, H:i'),
            'recipients' => data_get($run->data, 'recipients') ?: 'Not recorded',
            'format' => data_get($run->data, 'format') ?: 'Not recorded',
            'by' => $run->user?->name ?: ($run->user_id ? 'User #'.$run->user_id : 'System'),
            'status' => Str::headline((string) $run->status),
        ])->values()->all();
        $recipientList = $schedules->getCollection()->flatMap(fn (AdminRecord $schedule): array => $this->recipientValues((string) data_get($schedule->data, 'recipients')))->unique()->values()->all();
        $reportOptions = collect(array_merge(['Order Reports', 'Communication Reports', 'Customer Reports'], $schedules->getCollection()->pluck('data')->map(fn ($data) => data_get($data, 'report'))->filter()->all()))->merge(
            AdminRecord::query()->where('module', 'custom-reports')->where('status', 'active')->pluck('title')->all()
        )->unique()->values()->all();
        $scheduleSummary = $selectedSchedule ? [
            'report' => data_get($selectedSchedule->data, 'report') ?: $selectedSchedule->title,
            'frequency' => data_get($selectedSchedule->data, 'frequency') ?: 'Not recorded',
            'day' => data_get($selectedSchedule->data, 'day') ?: 'Not recorded',
            'start_date' => data_get($selectedSchedule->data, 'start_date') ?: 'Not recorded',
            'time' => data_get($selectedSchedule->data, 'time') ?: 'Not recorded',
            'timezone' => data_get($selectedSchedule->data, 'timezone') ?: 'Not recorded',
            'recipients' => data_get($selectedSchedule->data, 'recipients') ?: 'Not recorded',
            'format' => data_get($selectedSchedule->data, 'format') ?: 'Not recorded',
            'orientation' => data_get($selectedSchedule->data, 'orientation') ?: 'Not recorded',
            'status' => Str::headline((string) $selectedSchedule->status),
            'next_run' => $this->nextScheduleRun($selectedSchedule),
        ] : null;
        $selectedRecipients = $selectedSchedule ? (string) data_get($selectedSchedule->data, 'recipients', '') : '';
        $now = now();

        return [
            'hasScheduleData' => $schedules->total() > 0,
            'dataNote' => $schedules->total() > 0 ? 'Live database data · '.$schedules->total().' saved schedules' : 'No report schedules have been saved yet.',
            'schedules' => $schedules,
            'selectedSchedule' => $selectedSchedule,
            'selectedScheduleId' => $selectedSchedule?->id,
            'scheduleSummary' => $scheduleSummary,
            'deliveryHistory' => $deliveryHistory,
            'recipients' => $recipientList,
            'reportOptions' => $reportOptions,
            'formDefaults' => [
                'name' => '',
                'description' => '',
                'active' => true,
                'start_date' => $now->toDateString(),
                'end_date' => '',
                'time' => $now->format('H:i'),
                'timezone' => config('app.timezone', 'UTC'),
                'report' => $reportOptions[0] ?? '',
                'format' => 'CSV',
                'orientation' => 'landscape',
                'frequency' => 'Weekly',
                'day' => [],
                'recipients' => $selectedRecipients,
                'email_subject' => '',
                'email_message' => '',
                'reply_to' => '',
                'max_records' => '',
            ],
        ];
    }

    private function approvalAggregate(string $label, Collection $rows, ?string $role = null): array
    {
        $decided = $rows->filter(fn (Approval $approval): bool => $approval->decided_at !== null);
        $durations = $decided->map(fn (Approval $approval): float => max(0, $approval->created_at?->diffInSeconds($approval->decided_at) ?? 0) / 86400)->filter(fn (float $value): bool => $value > 0);
        $pending = $rows->whereIn('status', ['pending', 'in-progress'])->count();

        return [
            'label' => $label,
            'role' => $role,
            'requests' => $rows->count(),
            'approved' => $rows->where('status', 'approved')->count(),
            'pending' => $pending,
            'rejected' => $rows->where('status', 'rejected')->count(),
            'tat' => $durations->isNotEmpty() ? number_format((float) $durations->avg(), 2).' Days' : '—',
        ];
    }

    private function permissionRows(Collection $permissions): array
    {
        return $permissions->groupBy(fn (Permission $permission): string => (string) ($permission->module ?: $permission->group ?: 'Not recorded'))
            ->map(function (Collection $rows, string $module): array {
                $values = array_fill_keys(['view', 'create', 'edit', 'delete', 'export'], '—');
                $levels = [];
                foreach ($rows as $permission) {
                    $action = strtolower((string) $permission->action);
                    if (array_key_exists($action, $values)) {
                        $values[$action] = '✓';
                    }
                    $levels[] = (string) ($permission->pivot?->access_level ?: 'view');
                }
                $access = in_array('full', $levels, true) ? 'Full Access' : (in_array('limited', $levels, true) ? 'Limited Access' : 'View Access');

                return ['label' => $module, ...$values, 'access' => $access, 'permission_count' => $rows->count()];
            })->sortKeys()->values()->all();
    }

    private function returnProductRows(Collection $returns): array
    {
        $rows = [];
        foreach ($returns as $return) {
            $items = $return->order?->items ?: collect();
            $itemNames = $items->map(fn ($item): string => trim((string) ($item->name ?: 'Not recorded')))->filter()->unique()->values();
            if ($itemNames->isEmpty()) {
                continue;
            }
            $allocation = max(1, $itemNames->count());
            $amount = (float) ($return->order?->total ?: 0) / $allocation;
            foreach ($itemNames as $name) {
                $rows[$name]['name'] = $name;
                $rows[$name]['returns'] = ($rows[$name]['returns'] ?? 0) + 1;
                $rows[$name]['refund_amount'] = ($rows[$name]['refund_amount'] ?? 0) + (($return->status === 'refunded' || $return->type === 'refund') ? $amount : 0);
                $rows[$name]['currency'] = $return->order?->currency ?: 'EUR';
            }
        }

        return collect($rows)->sortByDesc('returns')->take(8)->map(fn (array $row): array => [
            'name' => $row['name'],
            'returns' => $row['returns'],
            'refunds' => $this->moneyLabel((float) $row['refund_amount'], strtoupper((string) $row['currency'])),
        ])->values()->all();
    }

    private function bucketRows(Collection $rows, Carbon $from, Carbon $to, callable $value): array
    {
        $span = max(1, $from->diffInDays($to));
        $points = [];
        for ($index = 0; $index < 7; $index++) {
            $start = $from->copy()->addDays((int) round(($span * $index) / 7))->startOfDay();
            $end = $index === 6
                ? $to->copy()->endOfDay()
                : $from->copy()->addDays((int) round(($span * ($index + 1)) / 7))->endOfDay();
            $matches = $rows->filter(function ($row) use ($start, $end): bool {
                $created = $row->created_at ?? null;
                return $created !== null && $created->between($start, $end, true);
            });
            $points[] = ['label' => $start->format('d M'), 'value' => $value($matches)];
        }

        return $points;
    }

    private function reportRecordCount(?string $module): int
    {
        $query = Order::query();
        $type = array_search($module, self::ORDER_TYPES, true);
        if ($type !== false) {
            $query->where('order_type', $type);
        }

        return $query->count();
    }

    private function exportRows(Request $request): array
    {
        return AdminRecord::query()
            ->whereIn('module', ['report-runs', 'custom-reports', 'report-schedules'])
            ->where('status', '!=', 'deleted')
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.$request->string('q').'%'))
            ->latest('created_at')
            ->get()
            ->map(fn (AdminRecord $record): array => [
                $record->title,
                $record->module,
                $record->status,
                data_get($record->data, 'records'),
                $record->created_at?->format('Y-m-d H:i'),
            ])->all();
    }

    private function titles(): array
    {
        return [
            'approvals' => ['title' => 'Approval Reports', 'subtitle' => 'Monitor all approval activities, turnaround times and decision trends.'],
            'custom' => ['title' => 'Custom Reports', 'subtitle' => 'Build and save reports from persisted order and customer data.'],
            'history' => ['title' => 'Report History', 'subtitle' => 'View and manage all persisted report runs, deliveries and downloads.'],
            'returns' => ['title' => 'Returns & Refund Reports', 'subtitle' => 'Track recorded returns, linked refunds and reasons to improve customer satisfaction and reduce losses.'],
            'roles' => ['title' => 'User Roles & Permissions', 'subtitle' => 'Manage user roles, permissions and access control across the system.'],
            'scheduler' => ['title' => 'Schedule Report', 'subtitle' => 'Save report delivery schedules for the configured reporting process.'],
        ];
    }

    private function metric(string $label, string $value, ?string $change, string $tone, string $icon): array
    {
        return compact('label', 'value', 'change', 'tone', 'icon');
    }

    private function share(int|float $value, int|float $total): string
    {
        return number_format($total > 0 ? ($value / $total) * 100 : 0, 2);
    }

    private function barPercent(int|float $value, int|float $max): float
    {
        return min(100, max(0, $max > 0 ? ($value / $max) * 100 : 0));
    }

    private function statusTone(string $status): string
    {
        return match (strtolower($status)) {
            'success', 'approved', 'active', 'healthy' => 'green',
            'failed', 'rejected', 'deleted', 'cancelled', 'expired' => 'red',
            'pending', 'in-progress', 'escalated', 'paused' => 'orange',
            default => 'blue',
        };
    }

    private function durationLabel(float $seconds): string
    {
        if ($seconds >= 3600) {
            return intdiv((int) $seconds, 3600).'h '.intdiv((int) $seconds % 3600, 60).'m';
        }
        if ($seconds >= 60) {
            return intdiv((int) $seconds, 60).'m '.((int) $seconds % 60).'s';
        }

        return number_format(max(0, $seconds), 2).'s';
    }

    private function currencyFor(Collection $rows): string
    {
        $currencies = $rows->map(function ($row): string {
            if ($row instanceof Order) {
                return strtoupper((string) ($row->currency_code ?: $row->currency ?: 'EUR'));
            }
            if ($row instanceof ReturnRequest) {
                return strtoupper((string) ($row->order?->currency_code ?: $row->order?->currency ?: 'EUR'));
            }

            return 'EUR';
        })->unique()->values();

        return $currencies->count() === 1 ? (string) $currencies->first() : ($currencies->count() > 1 ? 'MIXED' : 'EUR');
    }

    private function moneyLabel(float $amount, string $currency): string
    {
        if ($currency === 'MIXED') {
            return 'Multiple currencies';
        }

        return $this->currencySymbol($currency).number_format($amount, 2);
    }

    private function currencySymbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'GBP' => '£',
            'USD' => '$',
            'CHF' => 'CHF ',
            default => '€',
        };
    }

    private function normaliseRecipients(string $recipients, array $extra = []): string
    {
        $values = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\n]+/', implode(',', array_merge([$recipients], $extra))) ?: []))));
        $invalid = array_filter($values, static fn (string $value): bool => ! filter_var($value, FILTER_VALIDATE_EMAIL));
        if ($invalid || $values === []) {
            throw ValidationException::withMessages(['recipients' => 'Enter at least one valid recipient email address.']);
        }

        return implode(', ', $values);
    }

    private function recipientValues(string $recipients): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', $recipients) ?: [])));
    }

    private function nextScheduleRun(?AdminRecord $schedule): string
    {
        if (! $schedule || $schedule->status !== 'active') {
            return $schedule ? 'Paused' : 'Not scheduled';
        }
        $time = (string) data_get($schedule->data, 'time', '');
        $frequency = strtolower((string) data_get($schedule->data, 'frequency', ''));
        if (! preg_match('/^\d{2}:\d{2}$/', $time) || $frequency === '') {
            return 'Not calculated';
        }
        try {
            $candidate = Carbon::parse((string) (data_get($schedule->data, 'start_date') ?: now()->toDateString()))->setTimeFromTimeString($time);
        } catch (\Throwable) {
            return 'Not calculated';
        }
        while ($candidate->lessThanOrEqualTo(now())) {
            $candidate = match ($frequency) {
                'daily' => $candidate->addDay(),
                'weekly' => $candidate->addWeek(),
                'monthly' => $candidate->addMonthNoOverflow(),
                'quarterly' => $candidate->addMonthsNoOverflow(3),
                'yearly', 'annual' => $candidate->addYear(),
                default => $candidate->addWeek(),
            };
        }

        return $candidate->format('d M Y, H:i');
    }
}
