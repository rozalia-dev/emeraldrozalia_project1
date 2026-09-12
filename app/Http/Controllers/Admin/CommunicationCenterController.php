<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\AdminRecord;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\ApprovalRequestService;
use App\Services\CommunicationTemplateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunicationCenterController extends Controller
{
    public const SECTIONS = [
        'communication-center',
        'inbox',
        'chat-24-7',
        'whatsapp',
        'email',
        'email-templates',
        'approval-center',
        'action-follow-ups',
        'alerts-notifications',
        'communication-reports',
        'communication-history',
    ];

    private const CONVERSATION_SECTIONS = [
        'communication-center',
        'inbox',
        'chat-24-7',
        'whatsapp',
        'email',
    ];

    private const RECORD_SECTIONS = [
        'approval-center',
        'action-follow-ups',
        'alerts-notifications',
    ];

    public function show(Request $request, string $section): View
    {
        $config = $this->config($section);
        abort_unless($config, 404);

        $common = [
            'section' => $section,
            'config' => $config,
            'navigation' => $this->navigation(),
            'search' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'priority' => (string) $request->query('priority', ''),
            'tab' => (string) $request->query('tab', $section === 'communication-reports' ? 'overview' : 'all'),
            'date_from' => $this->displayDate($request->query('date_from'), now()->subDays(30)->toDateString()),
            'date_to' => $this->displayDate($request->query('date_to'), now()->toDateString()),
            'date_filtered' => $request->filled('date_from') || $request->filled('date_to'),
            'admins' => User::query()->where('is_admin', true)->orderBy('name')->get(['id', 'name']),
        ];

        if ($section === 'communication-reports') {
            return view('admin.communication-center.dashboard', $common + [
                'metrics' => $this->reportMetrics(),
                'report' => $this->reportData(),
                'conversations' => null,
                'selected' => null,
                'records' => null,
                'activities' => null,
            ]);
        }

        if ($section === 'communication-history') {
            $query = AuditLog::query()->latest('created_at');
            $this->applyAuditFilters($query, $request);
            $activities = $query->paginate(10)->withQueryString();
            $userIds = $activities->getCollection()->pluck('user_id')->filter()->unique()->values();
            $auditUsers = User::query()->whereIn('id', $userIds)->pluck('name', 'id');

            return view('admin.communication-center.dashboard', $common + [
                'metrics' => $this->auditMetrics(),
                'report' => $this->auditSummary(),
                'conversations' => null,
                'selected' => null,
                'records' => null,
                'activities' => $activities,
                'auditUsers' => $auditUsers,
            ]);
        }

        if ($section === 'email-templates') {
            $query = $this->templateQuery();
            $this->applyTemplateFilters($query, $request, $config);
            $records = $query->paginate(10)->withQueryString();

            return view('admin.communication-center.dashboard', $common + [
                'metrics' => $this->templateMetrics(),
                'report' => $this->templateSummary(),
                'conversations' => null,
                'selected' => null,
                'records' => $records,
                'activities' => null,
            ]);
        }

        if (in_array($section, self::CONVERSATION_SECTIONS, true)) {
            $query = $this->conversationQuery($section);
            $this->applyConversationFilters($query, $request);
            $conversations = $query->paginate($section === 'communication-center' ? 8 : 10)->withQueryString();

            $selectedKey = (string) $request->query('conversation', '');
            $selected = $section === 'email' && $selectedKey !== ''
                ? $this->conversationQuery($section)->where('uuid', $selectedKey)->first()
                : ((int) $selectedKey > 0
                    ? $this->conversationQuery($section)->whereKey((int) $selectedKey)->first()
                    : null);
            $selected ??= $conversations->getCollection()->first();

            return view('admin.communication-center.dashboard', $common + [
                'metrics' => $this->conversationMetrics($section),
                'report' => $section === 'communication-center' ? $this->reportData() : $this->conversationSideData($section),
                'conversations' => $conversations,
                'selected' => $selected,
                'records' => null,
                'activities' => null,
            ]);
        }

        if ($section === 'approval-center') {
            $query = $this->approvalQuery();
            $this->applyApprovalFilters($query, $request, $config);
            $records = $query->paginate(10)->withQueryString();

            return view('admin.communication-center.dashboard', $common + [
                'metrics' => $this->approvalMetrics(),
                'report' => $this->approvalSummary(),
                'conversations' => null,
                'selected' => null,
                'records' => $records,
                'activities' => null,
            ]);
        }

        $query = AdminRecord::query()->where('module', $section);
        $this->applyRecordFilters($query, $request, $config);
        $records = $query->latest('id')->paginate(10)->withQueryString();

        return view('admin.communication-center.dashboard', $common + [
            'metrics' => $this->recordMetrics($section),
            'report' => $this->recordSummary($section),
            'conversations' => null,
            'selected' => null,
            'records' => $records,
            'activities' => null,
        ]);
    }

    public function storeRecord(Request $request, string $section)
    {
        $config = $this->config($section);
        abort_unless($config && in_array($section, self::RECORD_SECTIONS, true), 404);

        $data = $this->validatedRecord($request, $config);
        if ($section === 'approval-center') {
            return $this->storeApprovalCompatibility($data, $config);
        }
        $record = AdminRecord::create($this->recordAttributes($section, $data));
        if (blank($record->reference)) {
            $record->update(['reference' => $this->referenceFor($section, $record->id)]);
        }

        AuditTrail::record('communication.'.$section.'.created', $record, null, $record->fresh()->toArray());

        return back()->with('success', $config['singular'].' created.');
    }

    public function updateRecord(Request $request, string $section, AdminRecord $record)
    {
        $config = $this->config($section);
        abort_unless($config && in_array($section, self::RECORD_SECTIONS, true), 404);
        $this->guardRecord($section, $record);

        if ($section === 'approval-center') {
            $data = $this->validatedRecord($request, $config);
            $approval = $this->approvalForShadow($record);
            if ($approval) {
                app(ApprovalRequestService::class)->update($approval, $data);
                $record->update([
                    'title' => $data['title'],
                    'status' => $data['status'],
                    'record_date' => $data['record_date'] ?? $record->record_date,
                    'data' => array_merge($record->data ?? [], $this->approvalShadowData($approval->fresh())),
                ]);

                return back()->with('success', $config['singular'].' updated.');
            }
        }

        $before = $record->toArray();
        $data = $this->validatedRecord($request, $config);
        $record->update($this->recordAttributes($section, $data, $record));

        AuditTrail::record('communication.'.$section.'.updated', $record, $before, $record->fresh()->toArray());

        return back()->with('success', $config['singular'].' updated.');
    }

    public function destroyRecord(string $section, AdminRecord $record)
    {
        $config = $this->config($section);
        abort_unless($config && in_array($section, self::RECORD_SECTIONS, true), 404);
        $this->guardRecord($section, $record);

        if ($section === 'approval-center') {
            $approval = $this->approvalForShadow($record);
            if ($approval) {
                app(ApprovalRequestService::class)->delete($approval);
            }
        }

        $before = $record->toArray();
        AuditTrail::record('communication.'.$section.'.deleted', $record, $before, null);
        $record->delete();

        return back()->with('success', $config['singular'].' deleted.');
    }

    public function recordAction(string $section, AdminRecord $record, string $action)
    {
        $config = $this->config($section);
        abort_unless($config && in_array($section, self::RECORD_SECTIONS, true), 404);
        $this->guardRecord($section, $record);

        if ($section === 'approval-center') {
            $approval = $this->approvalForShadow($record);
            if ($approval) {
                $decisionAction = match ($action) {
                    'approve' => 'approve',
                    'reject' => 'reject',
                    default => null,
                };
                abort_unless($decisionAction, 404);
                $updated = app(ApprovalRequestService::class)->decide($approval, $decisionAction);
                $record->update([
                    'status' => $updated->status,
                    'data' => array_merge($record->data ?? [], $this->approvalShadowData($updated)),
                ]);

                return back()->with('success', Str::headline($action).' completed.');
            }
        }

        $nextStatus = match ([$section, $action]) {
            ['approval-center', 'approve'] => 'approved',
            ['approval-center', 'reject'] => 'rejected',
            ['action-follow-ups', 'complete'] => 'completed',
            ['action-follow-ups', 'reopen'] => 'pending',
            ['alerts-notifications', 'acknowledge'] => 'acknowledged',
            ['alerts-notifications', 'resolve'] => 'resolved',
            default => null,
        };
        abort_unless($nextStatus, 404);

        $before = $record->toArray();
        $record->update(['status' => $nextStatus]);
        AuditTrail::record('communication.'.$section.'.'.$action, $record, $before, $record->fresh()->toArray());

        return back()->with('success', Str::headline($action).' completed.');
    }

    public function export(Request $request, string $section): StreamedResponse
    {
        $config = $this->config($section);
        abort_unless($config, 404);

        if ($section === 'communication-history') {
            $query = AuditLog::query()->latest('created_at');
            $this->applyAuditFilters($query, $request);
            $rows = $query->limit(10000)->get();

            return response()->streamDownload(function () use ($rows): void {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Date & Time', 'Action', 'Entity Type', 'Entity ID', 'User ID', 'IP Address', 'UUID']);
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        optional($row->created_at)->toDateTimeString(),
                        $row->action,
                        $row->subject_type,
                        $row->subject_id,
                        $row->user_id,
                        $row->ip_address,
                        $row->uuid,
                    ]);
                }
                fclose($handle);
            }, 'communication-history-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
        }

        if ($section === 'communication-reports') {
            $rows = collect($this->reportData()['channels']);

            return response()->streamDownload(function () use ($rows): void {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Channel', 'Conversations', 'Share']);
                foreach ($rows as $row) {
                    fputcsv($handle, [$row['label'], $row['count'], $row['share'].'%']);
                }
                fclose($handle);
            }, 'communication-report-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
        }

        if ($section === 'email-templates') {
            $query = $this->templateQuery();
            $this->applyTemplateFilters($query, $request, $config);
            $rows = $query->limit(10000)->get();
            AuditTrail::record('communication.email-template.exported', null, null, ['count' => $rows->count()]);

            return response()->streamDownload(function () use ($rows): void {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['UUID', 'Name', 'Subject', 'Channel', 'Category', 'Language', 'Status', 'Version', 'Body', 'Updated']);
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->uuid,
                        $row->name,
                        $row->subject,
                        $row->channel,
                        data_get($row->variables, 'category', 'General'),
                        data_get($row->variables, 'language', 'English'),
                        $row->status,
                        $row->version,
                        $row->body,
                        optional($row->updated_at)->toDateTimeString(),
                    ]);
                }
                fclose($handle);
            }, 'email-templates-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
        }

        if (in_array($section, self::CONVERSATION_SECTIONS, true)) {
            $query = $this->conversationQuery($section);
            $this->applyConversationFilters($query, $request);
            $rows = $query->limit(10000)->get();

            return response()->streamDownload(function () use ($rows): void {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['UUID', 'Channel', 'Contact', 'Subject', 'Status', 'Priority', 'Assigned To', 'Follow-up', 'Created']);
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->uuid,
                        $row->channel,
                        $row->contact,
                        $row->subject,
                        $row->status,
                        $row->priority,
                        $row->assignee?->name,
                        optional($row->follow_up_at)->toDateTimeString(),
                        optional($row->created_at)->toDateTimeString(),
                    ]);
                }
                fclose($handle);
            }, $section.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
        }

        if ($section === 'approval-center') {
            $query = $this->approvalQuery();
            $this->applyApprovalFilters($query, $request, $config);
            $rows = $query->limit(10000)->get();

            return response()->streamDownload(function () use ($rows): void {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['UUID', 'Reference', 'Type', 'Title', 'Status', 'Priority', 'Requested By', 'Approver', 'Entity', 'Due By', 'Created']);
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->uuid,
                        $row->reference,
                        $row->request_type,
                        $row->title,
                        $row->status,
                        $row->priority,
                        $row->requester_name ?: $row->requestedBy?->name,
                        $row->approver_name ?: $row->approver?->name,
                        $row->entity,
                        optional($row->due_at)->toDateTimeString(),
                        optional($row->created_at)->toDateTimeString(),
                    ]);
                }
                fclose($handle);
            }, 'approval-center-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
        }

        $query = AdminRecord::query()->where('module', $section);
        $this->applyRecordFilters($query, $request, $config);
        $rows = $query->latest('id')->limit(10000)->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reference', 'Title', 'Status', 'Date', 'Amount', 'Metadata']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->reference,
                    $row->title,
                    $row->status,
                    optional($row->record_date)->format('Y-m-d'),
                    $row->amount,
                    json_encode($row->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
            fclose($handle);
        }, $section.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function conversationQuery(string $section): Builder
    {
        $query = Conversation::query()
            ->forCurrentCompany()
            ->with(['messages' => fn ($messages) => $messages->oldest('id'), 'assignee', 'customer', 'order'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        return $this->scopeConversationChannel($query, $section);
    }

    private function scopeConversationChannel(Builder $query, string $section): Builder
    {
        return match ($section) {
            'chat-24-7' => $query->where('channel', 'chat'),
            'whatsapp' => $query->where('channel', 'whatsapp'),
            'email' => $query->where('channel', 'email'),
            default => $query,
        };
    }

    private function applyConversationFilters(Builder $query, Request $request): void
    {
        $isEmail = $request->is('admin/resource/email')
            || $request->is('admin/communication-center/email/export')
            || $request->route('section') === 'email';
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            if ($isEmail) {
                $this->applyEmailSearch($query, $search);
            } else {
                $query->where(function (Builder $builder) use ($search): void {
                    $builder->where('contact', 'like', '%'.$search.'%')
                        ->orWhere('subject', 'like', '%'.$search.'%')
                        ->orWhere('uuid', 'like', '%'.$search.'%');
                });
            }
        }

        if ($isEmail) {
            $this->applyEmailFieldFilters($query, $request);
        }

        $status = (string) $request->query('status', '');
        if (in_array($status, ['new', 'open', 'pending', 'closed'], true)) {
            $query->where('status', $status);
        }

        $priority = (string) $request->query('priority', '');
        if (in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $query->where('priority', $priority);
        }

        $channel = (string) $request->query('channel', '');
        if (in_array($channel, ['email', 'whatsapp', 'chat', 'web', 'phone', 'system'], true)) {
            $query->where('channel', $channel);
        }
    }

    private function applyEmailSearch(Builder $query, string $search): void
    {
        $like = '%'.$search.'%';
        $query->where(function (Builder $builder) use ($like): void {
            $builder->where('contact', 'like', $like)
                ->orWhere('subject', 'like', $like)
                ->orWhere('uuid', 'like', $like)
                ->orWhereHas('messages', fn (Builder $messages) => $messages
                    ->where('body', 'like', $like)
                    ->orWhere('uuid', 'like', $like))
                ->orWhereHas('customer', fn (Builder $customer) => $customer
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('public_uuid', 'like', $like))
                ->orWhereHas('order', fn (Builder $order) => $order
                    ->where('number', 'like', $like)
                    ->orWhere('public_uuid', 'like', $like));
            $this->orWhereConversationMetadata($builder, $like);
        });
    }

    private function applyEmailFieldFilters(Builder $query, Request $request): void
    {
        foreach (['customer', 'order', 'uid'] as $field) {
            $value = trim((string) $request->query($field, ''));
            if ($value === '') {
                continue;
            }

            $like = '%'.$value.'%';
            $query->where(function (Builder $builder) use ($field, $like): void {
                if ($field === 'customer') {
                    $builder->where('contact', 'like', $like)
                        ->orWhereHas('customer', fn (Builder $customer) => $customer
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('public_uuid', 'like', $like));
                } elseif ($field === 'order') {
                    $builder->whereHas('order', fn (Builder $order) => $order
                        ->where('number', 'like', $like)
                        ->orWhere('public_uuid', 'like', $like));
                    $this->orWhereConversationMetadata($builder, $like);
                } else {
                    $builder->where('uuid', 'like', $like)
                        ->orWhereHas('messages', fn (Builder $messages) => $messages->where('uuid', 'like', $like));
                    $this->orWhereConversationMetadata($builder, $like);
                }
            });
        }

        if ($from = $this->dateFilter($request->query('date_from'))) {
            $query->where('created_at', '>=', $from->startOfDay());
        }
        if ($to = $this->dateFilter($request->query('date_to'))) {
            $query->where('created_at', '<=', $to->endOfDay());
        }
    }

    private function orWhereConversationMetadata(Builder $query, string $like): void
    {
        $column = $query->getModel()->getTable().'.metadata';
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            $query->orWhereRaw($column.'::text ILIKE ?', [$like]);
        } elseif ($query->getConnection()->getDriverName() === 'mysql') {
            $query->orWhereRaw('CAST('.$column.' AS CHAR) LIKE ?', [$like]);
        } else {
            $query->orWhere($column, 'like', $like);
        }
    }

    private function dateFilter(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', trim($value));
        } catch (\Throwable) {
            return null;
        }
    }

    private function displayDate(mixed $value, string $fallback): string
    {
        return ($date = $this->dateFilter($value))?->toDateString() ?? $fallback;
    }

    private function approvalQuery(): Builder
    {
        return Approval::query()
            ->forCurrentCompany()
            ->with(['requestedBy', 'approver', 'decidedBy'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    private function applyApprovalFilters(Builder $query, Request $request, array $config): void
    {
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('uuid', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('entity', 'like', $like)
                    ->orWhere('source', 'like', $like)
                    ->orWhereHas('requestedBy', fn (Builder $user) => $user->where('name', 'like', $like))
                    ->orWhereHas('approver', fn (Builder $user) => $user->where('name', 'like', $like));
            });
        }

        $status = (string) $request->query('status', '');
        $tab = (string) $request->query('tab', 'all');
        if ($status === '' && isset($config['statuses'][$tab])) {
            $status = $tab;
        }
        if (isset($config['statuses'][$status])) {
            $query->where('status', $status);
        }

        $priority = Str::lower((string) $request->query('priority', ''));
        if (in_array($priority, ['low', 'medium', 'normal', 'high', 'urgent'], true)) {
            $query->where('priority', $priority);
        }

        $type = trim((string) $request->query('type', ''));
        if ($type !== '') {
            $query->where('request_type', $type);
        }
        if ($from = $this->dateFilter($request->query('date_from'))) {
            $query->where('record_date', '>=', $from->toDateString());
        }
        if ($to = $this->dateFilter($request->query('date_to'))) {
            $query->where('record_date', '<=', $to->toDateString());
        }
    }

    private function applyRecordFilters(Builder $query, Request $request, array $config): void
    {
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('reference', 'like', '%'.$search.'%');
            });
        }

        $status = (string) $request->query('status', '');
        $tab = (string) $request->query('tab', 'all');
        if ($status === '' && isset($config['statuses'][$tab])) {
            $status = $tab;
        }
        if (isset($config['statuses'][$status])) {
            $query->where('status', $status);
        }

        $priority = Str::lower((string) $request->query('priority', ''));
        if (in_array($priority, ['low', 'medium', 'normal', 'high', 'urgent'], true)) {
            $query->where('data->priority', $priority);
        }
    }

    private function templateQuery(): Builder
    {
        return CommunicationTemplate::query()
            ->forCurrentCompany()
            ->where('channel', 'email')
            ->latest('updated_at')
            ->latest('id');
    }

    private function applyTemplateFilters(Builder $query, Request $request, array $config): void
    {
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $needle = '%'.$search.'%';
                $builder->where('name', 'like', $needle)
                    ->orWhere('subject', 'like', $needle)
                    ->orWhere('uuid', 'like', $needle);
            });
        }

        $status = (string) $request->query('status', '');
        $tab = (string) $request->query('tab', 'all');
        if ($status === '' && isset($config['statuses'][$tab])) {
            $status = $tab;
        }
        if (in_array($status, CommunicationTemplateService::STATUSES, true)) {
            $query->where('status', $status);
        }

        foreach (['category', 'language'] as $field) {
            if ($request->filled($field)) {
                $query->where('variables->'.$field, $request->query($field));
            }
        }
    }

    private function applyAuditFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('action', 'like', '%'.$search.'%')
                    ->orWhere('subject_type', 'like', '%'.$search.'%')
                    ->orWhere('request_id', 'like', '%'.$search.'%')
                    ->orWhere('ip_address', 'like', '%'.$search.'%');
            });
        }
    }

    private function conversationMetrics(string $section): array
    {
        $base = fn () => $this->scopeConversationChannel(Conversation::query(), $section);
        $total = $base()->count();
        $open = $base()->whereIn('status', ['new', 'open'])->count();
        $pending = $base()->where('status', 'pending')->count();
        $closed = $base()->where('status', 'closed')->count();
        $urgent = $base()->where('priority', 'urgent')->where('status', '!=', 'closed')->count();
        $followUps = $base()->whereNotNull('follow_up_at')->where('status', '!=', 'closed')->count();

        if ($section === 'communication-center') {
            $approvals = Approval::query()->forCurrentCompany()->whereIn('status', ['pending', 'in-progress'])->count();
            $alerts = AdminRecord::query()->where('module', 'alerts-notifications')->whereIn('status', ['unread', 'in-progress', 'escalated'])->count();

            return [
                $this->metric('Total Conversations', $total, 'message', 'blue', 'Live communication records'),
                $this->metric('Unread Messages', $base()->where('status', 'new')->count(), 'mail', 'green', 'Needs attention'),
                $this->metric('Pending Approvals', $approvals, 'briefcase', 'orange', 'Waiting for decision'),
                $this->metric('Open Follow-ups', $followUps, 'clock', 'purple', 'Scheduled commitments'),
                $this->metric('Alerts & Notifications', $alerts, 'bell', 'red', 'Open alerts'),
                $this->metric('Resolved (This Month)', $closed, 'check', 'teal', 'Closed conversations'),
            ];
        }

        if ($section === 'chat-24-7') {
            $answered = ConversationMessage::query()
                ->where('direction', 'outbound')
                ->whereHas('conversation', fn (Builder $query) => $this->scopeConversationChannel($query, $section))
                ->count();

            return [
                $this->metric('Active Chats', $open, 'message', 'green', 'Open live sessions'),
                $this->metric('Waiting', $pending, 'clock', 'orange', 'Waiting for agent'),
                $this->metric('Answered (This Month)', $answered, 'check', 'blue', 'Stored outbound replies'),
                $this->metric('Avg. Response Time', $this->averageResponseTime($section), 'clock', 'purple', 'From captured response data'),
                $this->metric('Satisfaction (CSAT)', $this->averageCsat($section), 'star', 'green', 'Customer rating'),
                $this->metric('Missed Chats', $urgent, 'alert', 'red', 'Urgent unresolved'),
                $this->metric('SLA Met', $this->slaRate($total, $urgent), 'check', 'teal', 'Current communication SLA'),
            ];
        }

        if ($section === 'whatsapp') {
            return [
                $this->metric('Conversations (This Month)', $total, 'message', 'green', 'WhatsApp threads'),
                $this->metric('New Contacts', $this->uniqueContacts($section), 'users', 'blue', 'Unique contacts'),
                $this->metric('Avg. Response Time', $this->averageResponseTime($section), 'clock', 'orange', 'Captured response data'),
                $this->metric('Resolved', $closed, 'check', 'purple', 'Closed threads'),
                $this->metric('Active Contacts', $open + $pending, 'users', 'green', 'Active contacts'),
                $this->metric('SLA Met', $this->slaRate($total, $urgent), 'clock', 'red', 'Current SLA'),
            ];
        }

        if ($section === 'email') {
            $currentMonth = fn () => $base()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
            $sent = ConversationMessage::query()
                ->where('direction', 'outbound')
                ->whereHas('conversation', fn (Builder $query) => $this->scopeConversationChannel($query, $section)
                    ->whereBetween('conversations.created_at', [now()->startOfMonth(), now()->endOfMonth()]))
                ->count();
            $replied = $currentMonth()->whereHas('messages', fn (Builder $query) => $query->where('direction', 'outbound'))->count();

            return [
                $this->metric('Emails (This Month)', $currentMonth()->count(), 'mail', 'green', 'Tracked email threads'),
                $this->metric('Open', $open, 'briefcase', 'blue', 'Open email work'),
                $this->metric('Sent', $sent, 'message', 'orange', 'Outbound messages'),
                $this->metric('Replied', $replied, 'refresh', 'purple', 'Threads with replies'),
                $this->metric('Resolved', $currentMonth()->where('status', 'closed')->count(), 'check', 'green', 'Resolved email threads'),
                $this->metric('SLA Breaches', $urgent, 'alert', 'red', 'Urgent unresolved'),
            ];
        }

        return [
            $this->metric('Total Conversations', $total, 'message', 'green', 'All channels'),
            $this->metric('Open', $open, 'message', 'blue', 'New and open'),
            $this->metric('Pending', $pending, 'briefcase', 'orange', 'Waiting'),
            $this->metric('In Progress', $open, 'clock', 'purple', 'Being handled'),
            $this->metric('Resolved', $closed, 'check', 'green', 'Resolved'),
            $this->metric('Closed', $closed, 'check', 'dark', 'Closed'),
            $this->metric('SLA Breaches', $urgent, 'alert', 'red', 'Urgent unresolved'),
        ];
    }

    private function approvalMetrics(): array
    {
        $query = Approval::query()->forCurrentCompany();
        $resolved = (clone $query)->whereIn('status', ['approved', 'rejected'])->whereNotNull('decided_at')->get(['created_at', 'decided_at']);
        $durations = $resolved->map(fn (Approval $approval) => $approval->created_at && $approval->decided_at
            ? $approval->created_at->diffInSeconds($approval->decided_at)
            : null)->filter(fn ($seconds): bool => is_numeric($seconds));
        $average = $durations->isEmpty() ? '—' : $this->durationLabel((int) round((float) $durations->average()));

        return [
            $this->metric('Total Requests', (clone $query)->count(), 'briefcase', 'blue', 'Durable approval requests'),
            $this->metric('Pending', (clone $query)->where('status', 'pending')->count(), 'briefcase', 'orange', 'Awaiting approval'),
            $this->metric('In Progress', (clone $query)->whereIn('status', ['in-progress', 'escalated'])->count(), 'briefcase', 'purple', 'Being reviewed'),
            $this->metric('Approved', (clone $query)->where('status', 'approved')->count(), 'check', 'green', 'Approved requests'),
            $this->metric('Rejected', (clone $query)->where('status', 'rejected')->count(), 'alert', 'red', 'Rejected requests'),
            $this->metric('Avg. Approval Time', $average, 'clock', 'dark', 'Stored decision duration'),
        ];
    }

    private function recordMetrics(string $section): array
    {
        if ($section === 'email-templates') {
            return $this->templateMetrics();
        }

        if ($section === 'approval-center') {
            return $this->approvalMetrics();
        }

        $rows = AdminRecord::query()->where('module', $section)->get();
        $count = fn (string ...$statuses) => $rows->whereIn('status', $statuses)->count();

        if ($section === 'approval-center') {
            return [
                $this->metric('Total Requests', $rows->count(), 'briefcase', 'blue', 'All approval requests'),
                $this->metric('Pending', $count('pending'), 'briefcase', 'orange', 'Awaiting approval'),
                $this->metric('In Progress', $count('in-progress', 'escalated'), 'briefcase', 'purple', 'Being reviewed'),
                $this->metric('Approved', $count('approved'), 'check', 'green', 'Approved requests'),
                $this->metric('Rejected', $count('rejected'), 'alert', 'red', 'Rejected requests'),
                $this->metric('Avg. Approval Time', $this->averageMetaDuration($rows, 'approval_seconds'), 'clock', 'dark', 'Captured workflow time'),
            ];
        }

        if ($section === 'action-follow-ups') {
            return [
                $this->metric('Total Actions', $rows->count(), 'check', 'green', 'All follow-ups'),
                $this->metric('Pending', $count('pending'), 'clock', 'orange', 'Waiting'),
                $this->metric('In Progress', $count('in-progress'), 'message', 'blue', 'Active tasks'),
                $this->metric('Completed', $count('completed'), 'check', 'purple', 'Finished'),
                $this->metric('Overdue', $count('overdue'), 'alert', 'red', 'Needs attention'),
                $this->metric('Avg. Completion Time', $this->averageMetaDuration($rows, 'completion_seconds'), 'clock', 'dark', 'Captured duration'),
            ];
        }

        return [
            $this->metric('Critical', $this->metaCount($rows, 'severity', 'critical'), 'bell', 'red', 'Critical alerts'),
            $this->metric('High', $this->metaCount($rows, 'severity', 'high'), 'alert', 'orange', 'High severity'),
            $this->metric('Medium', $this->metaCount($rows, 'severity', 'medium'), 'help', 'orange', 'Medium severity'),
            $this->metric('Low', $this->metaCount($rows, 'severity', 'low'), 'help', 'blue', 'Low severity'),
            $this->metric('Unread', $count('unread'), 'mail', 'purple', 'Unread alerts'),
            $this->metric('Acknowledged', $count('acknowledged'), 'check', 'green', 'Acknowledged'),
            $this->metric('Avg. Response Time', $this->averageMetaDuration($rows, 'response_seconds'), 'clock', 'dark', 'Captured response time'),
        ];
    }

    private function reportMetrics(): array
    {
        $total = Conversation::query()->count();
        $messages = ConversationMessage::query()->where('direction', 'outbound')->count();
        $unique = Conversation::query()->whereNotNull('contact')->distinct()->count('contact');
        $urgent = Conversation::query()->where('priority', 'urgent')->where('status', '!=', 'closed')->count();
        $closed = Conversation::query()->where('status', 'closed')->count();

        return [
            $this->metric('Total Conversations', $total, 'message', 'green', 'All communication channels'),
            $this->metric('Messages Sent', $messages, 'mail', 'blue', 'Stored messages'),
            $this->metric('Unique Customers', $unique, 'users', 'purple', 'Distinct contacts'),
            $this->metric('Avg. Response Time', $this->averageResponseTime('communication-center'), 'clock', 'orange', 'Captured response data'),
            $this->metric('Avg. Resolution Time', $this->averageResolutionTime(), 'check', 'teal', 'Captured resolution data'),
            $this->metric('Customer Satisfaction', $this->averageCsat('communication-center'), 'star', 'orange', 'Average CSAT'),
            $this->metric('SLA Breach Rate', $total ? number_format(($urgent / $total) * 100, 2).'%' : '0.00%', 'alert', 'red', $closed.' conversations resolved'),
        ];
    }

    private function auditMetrics(): array
    {
        $query = AuditLog::query();
        $total = $query->count();

        return [
            $this->metric('Total Activities', $total, 'message', 'green', 'Complete audit activity'),
            $this->metric('Messages Logged', (clone $query)->where('action', 'like', '%message%')->count(), 'mail', 'blue', 'Communication messages'),
            $this->metric('Attachments', (clone $query)->where('action', 'like', '%attachment%')->count(), 'briefcase', 'orange', 'Attachment events'),
            $this->metric('Actions Performed', (clone $query)->where('action', 'like', '%action%')->count(), 'star', 'purple', 'Action events'),
            $this->metric('Changes Logged', (clone $query)->where(function (Builder $builder): void {
                $builder->where('action', 'like', '%.updated%')->orWhere('action', 'like', '%changed%');
            })->count(), 'file-text', 'teal', 'Change events'),
            $this->metric('Users Involved', (clone $query)->whereNotNull('user_id')->distinct()->count('user_id'), 'users', 'red', 'Unique users'),
        ];
    }

    private function reportData(): array
    {
        $total = max(1, Conversation::query()->count());

        $channels = Conversation::query()
            ->selectRaw('channel, COUNT(*) AS aggregate')
            ->groupBy('channel')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => [
                'label' => Str::headline($row->channel ?: 'Other'),
                'count' => (int) $row->aggregate,
                'share' => round(((int) $row->aggregate / $total) * 100, 2),
            ])->values()->all();

        $statuses = Conversation::query()
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => [
                'label' => Str::headline($row->status ?: 'Unknown'),
                'count' => (int) $row->aggregate,
                'share' => round(((int) $row->aggregate / $total) * 100, 2),
            ])->values()->all();

        $trend = collect(range(6, 0))->map(function (int $daysAgo): array {
            $date = now()->subDays($daysAgo);

            return [
                'label' => $date->format('M j'),
                'count' => Conversation::query()->whereDate('created_at', $date->toDateString())->count(),
            ];
        })->all();

        $sample = Conversation::query()->latest('id')->limit(5000)->get(['id', 'subject', 'channel', 'status', 'priority', 'assigned_to', 'metadata']);
        $topics = $sample->groupBy(function (Conversation $conversation): string {
            return (string) data_get($conversation->metadata, 'topic', $this->topicFromSubject($conversation->subject));
        })->map->count()->sortDesc()->take(6)->map(function (int $count, string $label) use ($sample): array {
            $base = max(1, $sample->count());
            return ['label' => $label, 'count' => $count, 'share' => round(($count / $base) * 100, 2)];
        })->values()->all();

        $business = $sample->groupBy(function (Conversation $conversation): string {
            return Str::headline((string) data_get($conversation->metadata, 'order_category', data_get($conversation->metadata, 'business_activity', 'General')));
        })->map->count()->sortDesc()->take(7)->map(function (int $count, string $label) use ($sample): array {
            $base = max(1, $sample->count());
            return ['label' => $label, 'count' => $count, 'share' => round(($count / $base) * 100, 2)];
        })->values()->all();

        $agentRows = Conversation::query()
            ->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, COUNT(*) AS aggregate')
            ->groupBy('assigned_to')
            ->orderByDesc('aggregate')
            ->limit(5)
            ->get();
        $agentNames = User::query()->whereIn('id', $agentRows->pluck('assigned_to'))->pluck('name', 'id');
        $agents = $agentRows->map(fn ($row) => [
            'label' => $agentNames[$row->assigned_to] ?? 'Admin User',
            'count' => (int) $row->aggregate,
        ])->values()->all();

        $urgent = Conversation::query()->where('priority', 'urgent')->where('status', '!=', 'closed')->count();
        $sla = round(max(0, 100 - (($urgent / $total) * 100)), 2);

        return [
            'total' => Conversation::query()->count(),
            'channels' => $channels,
            'statuses' => $statuses,
            'trend' => $trend,
            'topics' => $topics,
            'business' => $business,
            'agents' => $agents,
            'sla' => $sla,
            'csat' => $this->averageCsat('communication-center'),
            'avg_response' => $this->averageResponseTime('communication-center'),
            'avg_resolution' => $this->averageResolutionTime(),
            'open' => Conversation::query()->whereIn('status', ['new', 'open', 'pending'])->count(),
            'closed' => Conversation::query()->where('status', 'closed')->count(),
            'recent' => Conversation::query()->latest('id')->limit(5)->get(),
            'approval_recent' => Approval::query()->forCurrentCompany()->with(['requestedBy', 'approver'])->latest('id')->limit(5)->get(),
            'alert_recent' => AdminRecord::query()->where('module', 'alerts-notifications')->latest('id')->limit(5)->get(),
        ];
    }

    private function conversationSideData(string $section): array
    {
        $base = $this->scopeConversationChannel(Conversation::query(), $section);
        $total = max(1, (clone $base)->count());

        return [
            'open' => (clone $base)->whereIn('status', ['new', 'open'])->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'closed' => (clone $base)->where('status', 'closed')->count(),
            'urgent' => (clone $base)->where('priority', 'urgent')->where('status', '!=', 'closed')->count(),
            'total' => $total,
        ];
    }

    private function recordSummary(string $section): array
    {
        if ($section === 'email-templates') {
            return $this->templateSummary();
        }

        if ($section === 'approval-center') {
            return $this->approvalSummary();
        }

        $rows = AdminRecord::query()->where('module', $section)->latest('id')->get();

        return [
            'total' => $rows->count(),
            'recent' => $rows->take(5),
            'status_counts' => $rows->groupBy('status')->map->count()->sortDesc(),
            'category_counts' => $rows->groupBy(fn (AdminRecord $row) => (string) data_get($row->data, 'category', 'General'))->map->count()->sortDesc()->take(7),
            'source_counts' => $rows->groupBy(fn (AdminRecord $row) => (string) data_get($row->data, 'source', 'Communication Center'))->map->count()->sortDesc()->take(7),
            'priority_counts' => $rows->groupBy(fn (AdminRecord $row) => (string) data_get($row->data, 'priority', 'normal'))->map->count()->sortDesc(),
            'severity_counts' => $rows->groupBy(fn (AdminRecord $row) => (string) data_get($row->data, 'severity', 'low'))->map->count()->sortDesc(),
        ];
    }

    private function approvalSummary(): array
    {
        $rows = $this->approvalQuery()->latest('id')->limit(5000)->get();

        return [
            'total' => $rows->count(),
            'recent' => $rows->take(5),
            'status_counts' => $rows->groupBy('status')->map->count()->sortDesc(),
            'category_counts' => $rows->groupBy(fn (Approval $row) => (string) ($row->request_type ?: 'General'))->map->count()->sortDesc()->take(7),
            'source_counts' => $rows->groupBy(fn (Approval $row) => (string) ($row->source ?: 'Communication Center'))->map->count()->sortDesc()->take(7),
            'priority_counts' => $rows->groupBy(fn (Approval $row) => (string) ($row->priority ?: 'normal'))->map->count()->sortDesc(),
            'severity_counts' => collect(),
        ];
    }

    private function storeApprovalCompatibility(array $data, array $config): RedirectResponse
    {
        $approval = app(ApprovalRequestService::class)->create([
            'title' => $data['title'],
            'reference' => $data['reference'] ?? null,
            'type' => $data['type'] ?? null,
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'requested_by' => $data['requested_by'] ?? null,
            'approver' => $data['approver'] ?? null,
            'entity' => $data['entity'] ?? null,
            'source' => 'Communication Center',
            'due_at' => $data['due_at'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'record_date' => $data['record_date'] ?? now()->toDateString(),
        ]);

        $record = AdminRecord::create([
            'module' => 'approval-center',
            'title' => $approval->title,
            'reference' => $approval->reference,
            'status' => $approval->status,
            'record_date' => $approval->record_date,
            'user_id' => auth()->id(),
            'data' => $this->approvalShadowData($approval),
        ]);

        AuditTrail::record('communication.approval.compatibility-shadow.created', $record, null, [
            'approval_uuid' => $approval->uuid,
            'reference' => $approval->reference,
        ]);

        return back()->with('success', $config['singular'].' created.');
    }

    private function approvalForShadow(AdminRecord $record): ?Approval
    {
        $uuid = data_get($record->data, 'approval_uuid');
        $query = Approval::withTrashed()->withoutGlobalScopes();
        $approval = $uuid
            ? $query->where('uuid', $uuid)->first()
            : $query->where('reference', $record->reference)->first();

        if (! $approval) {
            return null;
        }

        if (session('company_id') && (int) $approval->company_id !== (int) session('company_id')) {
            abort(404);
        }

        return $approval;
    }

    private function approvalShadowData(Approval $approval): array
    {
        return [
            'approval_uuid' => $approval->uuid,
            'type' => $approval->request_type,
            'description' => $approval->description,
            'priority' => $approval->priority,
            'requested_by' => $approval->requester_name ?: $approval->requestedBy?->name,
            'approver' => $approval->approver_name ?: $approval->approver?->name,
            'entity' => $approval->entity,
            'source' => $approval->source,
            'due_at' => $approval->due_at?->toIso8601String(),
        ];
    }

    private function templateMetrics(): array
    {
        $query = CommunicationTemplate::query()->forCurrentCompany()->where('channel', 'email');
        $total = (clone $query)->count();
        $withVariables = (clone $query)->whereNotNull('variables')->get(['variables'])
            ->filter(fn (CommunicationTemplate $template): bool => is_array($template->variables) && $template->variables !== [])
            ->count();

        return [
            $this->metric('Total Templates', $total, 'mail', 'green', 'Durable email templates'),
            $this->metric('Active', (clone $query)->where('status', 'active')->count(), 'check', 'blue', 'Available to send'),
            $this->metric('Draft', (clone $query)->where('status', 'draft')->count(), 'file-text', 'orange', 'Still being edited'),
            $this->metric('Approval Required', (clone $query)->where('status', 'pending_approval')->count(), 'briefcase', 'purple', 'Awaiting review'),
            $this->metric('Archived', (clone $query)->where('status', 'archived')->count(), 'folder', 'red', 'Retained but inactive'),
            $this->metric('With Variables', $withVariables, 'settings', 'teal', 'Locale and merge metadata'),
        ];
    }

    private function templateSummary(): array
    {
        $rows = $this->templateQuery()->get();

        return [
            'total' => $rows->count(),
            'recent' => $rows->take(5),
            'status_counts' => $rows->groupBy('status')->map->count()->sortDesc(),
            'category_counts' => $rows->groupBy(fn (CommunicationTemplate $row) => (string) data_get($row->variables, 'category', 'General'))->map->count()->sortDesc()->take(7),
            'source_counts' => $rows->groupBy(fn (CommunicationTemplate $row) => (string) data_get($row->variables, 'language', 'English'))->map->count()->sortDesc()->take(7),
            'priority_counts' => collect(),
            'severity_counts' => collect(),
        ];
    }

    private function auditSummary(): array
    {
        $rows = AuditLog::query()->latest('created_at')->limit(5000)->get();

        return [
            'total' => $rows->count(),
            'by_action' => $rows->groupBy(fn (AuditLog $row) => Str::headline(Str::before($row->action, '.')))->map->count()->sortDesc()->take(7),
            'by_entity' => $rows->groupBy(fn (AuditLog $row) => class_basename((string) $row->subject_type ?: 'System'))->map->count()->sortDesc()->take(7),
            'recent' => $rows->take(5),
        ];
    }

    private function validatedRecord(Request $request, array $config): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'reference' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(array_keys($config['statuses']))],
            'record_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'subject' => ['nullable', 'string', 'max:250'],
            'description' => ['nullable', 'string', 'max:3000'],
            'category' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:120'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'normal', 'high', 'urgent'])],
            'assigned_to_name' => ['nullable', 'string', 'max:180'],
            'requested_by' => ['nullable', 'string', 'max:180'],
            'approver' => ['nullable', 'string', 'max:180'],
            'entity' => ['nullable', 'string', 'max:180'],
            'source' => ['nullable', 'string', 'max:120'],
            'due_at' => ['nullable', 'date'],
            'language' => ['nullable', 'string', 'max:80'],
            'channel' => ['nullable', 'string', 'max:80'],
            'body' => ['nullable', 'string', 'max:10000'],
            'severity' => ['nullable', Rule::in(['critical', 'high', 'medium', 'low', 'informational'])],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    private function recordAttributes(string $section, array $data, ?AdminRecord $existing = null): array
    {
        $metadataKeys = [
            'subject', 'description', 'category', 'type', 'priority', 'assigned_to_name',
            'requested_by', 'approver', 'entity', 'source', 'due_at', 'language', 'channel',
            'body', 'severity', 'notes',
        ];
        $metadata = [];
        foreach ($metadataKeys as $key) {
            if (array_key_exists($key, $data)) {
                $metadata[$key] = $data[$key];
            }
        }

        return [
            'module' => $section,
            'title' => $data['title'],
            'reference' => $data['reference'] ?? $existing?->reference,
            'status' => $data['status'],
            'amount' => $data['amount'] ?? null,
            'record_date' => filled($data['record_date'] ?? null) ? $data['record_date'] : ($existing?->record_date ?? now()->toDateString()),
            'user_id' => auth()->id(),
            'data' => array_merge($existing?->data ?? [], $metadata),
        ];
    }

    private function guardRecord(string $section, AdminRecord $record): void
    {
        abort_unless($record->module === $section, 404);
    }

    private function referenceFor(string $section, int $id): string
    {
        $prefix = match ($section) {
            'email-templates' => 'TPL',
            'approval-center' => 'APR',
            'action-follow-ups' => 'ACT',
            'alerts-notifications' => 'ALT',
            default => 'COM',
        };

        return $prefix.'-'.now()->format('ymd').'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    private function metric(string $label, mixed $value, string $icon, string $tone, string $sub): array
    {
        return compact('label', 'value', 'icon', 'tone', 'sub');
    }

    private function metaCount(Collection $rows, string $key, string $value): int
    {
        return $rows->filter(fn (AdminRecord $row) => Str::lower((string) data_get($row->data, $key)) === $value)->count();
    }

    private function sumMeta(Collection $rows, string $key): int
    {
        return (int) $rows->sum(fn (AdminRecord $row) => (int) data_get($row->data, $key, 0));
    }

    private function averageMetaDuration(Collection $rows, string $key): string
    {
        $values = $rows->map(fn (AdminRecord $row) => data_get($row->data, $key))
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (float) $value);
        if ($values->isEmpty()) {
            return '—';
        }

        return $this->durationLabel((int) round((float) $values->average()));
    }

    private function averageResponseTime(string $section): string
    {
        $rows = $this->scopeConversationChannel(Conversation::query(), $section)
            ->latest('id')->limit(2000)->get(['metadata']);
        $values = $rows->map(fn (Conversation $row) => data_get($row->metadata, 'first_response_seconds'))
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (float) $value);

        return $values->isEmpty() ? '—' : $this->durationLabel((int) round((float) $values->average()));
    }

    private function averageResolutionTime(): string
    {
        $values = Conversation::query()->latest('id')->limit(2000)->get(['metadata'])
            ->map(fn (Conversation $row) => data_get($row->metadata, 'resolution_seconds'))
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (float) $value);

        return $values->isEmpty() ? '—' : $this->durationLabel((int) round((float) $values->average()));
    }

    private function averageCsat(string $section): string
    {
        $rows = $this->scopeConversationChannel(Conversation::query(), $section)
            ->latest('id')->limit(2000)->get(['metadata']);
        $values = $rows->map(fn (Conversation $row) => data_get($row->metadata, 'csat'))
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (float) $value);

        return $values->isEmpty() ? '—' : number_format((float) $values->average(), 2).' / 5';
    }

    private function uniqueContacts(string $section): int
    {
        return $this->scopeConversationChannel(Conversation::query(), $section)
            ->whereNotNull('contact')->distinct()->count('contact');
    }

    private function slaRate(int $total, int $breaches): string
    {
        if ($total === 0) {
            return '100%';
        }

        return number_format(max(0, 100 - (($breaches / $total) * 100)), 2).'%';
    }

    private function durationLabel(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60).'m '.($seconds % 60).'s';
        }

        return intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m';
    }

    private function topicFromSubject(?string $subject): string
    {
        $subject = Str::lower((string) $subject);

        return match (true) {
            str_contains($subject, 'order') => 'Order Status',
            str_contains($subject, 'return'), str_contains($subject, 'refund') => 'Returns & Refunds',
            str_contains($subject, 'payment') => 'Payment Issues',
            str_contains($subject, 'franchise') => 'Franchise',
            str_contains($subject, 'product') => 'Product Information',
            str_contains($subject, 'deliver'), str_contains($subject, 'ship') => 'Delivery & Shipping',
            default => 'Other',
        };
    }

    private function navigation(): array
    {
        return [
            'communication-center' => ['label' => 'Overview', 'icon' => 'message'],
            'inbox' => ['label' => 'Inbox', 'icon' => 'mail'],
            'chat-24-7' => ['label' => 'Chat 24/7', 'icon' => 'message'],
            'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'message'],
            'email' => ['label' => 'Email', 'icon' => 'mail'],
            'email-templates' => ['label' => 'Email Templates', 'icon' => 'file-text'],
            'approval-center' => ['label' => 'Approval Center', 'icon' => 'check'],
            'action-follow-ups' => ['label' => 'Action / Follow-ups', 'icon' => 'clock'],
            'alerts-notifications' => ['label' => 'Alerts & Notifications', 'icon' => 'bell'],
            'communication-reports' => ['label' => 'Communication Reports', 'icon' => 'chart'],
            'communication-history' => ['label' => 'Communication History', 'icon' => 'file-text'],
        ];
    }

    private function config(string $section): ?array
    {
        $configs = [
            'communication-center' => [
                'title' => 'Communication Center',
                'subtitle' => 'Unified communication, approvals and follow-ups across all channels.',
                'icon' => 'message',
                'variant' => 'overview',
                'singular' => 'Conversation',
                'statuses' => [],
                'tabs' => [],
            ],
            'inbox' => [
                'title' => 'Inbox',
                'subtitle' => 'View, manage and respond to all incoming messages and tickets from every channel.',
                'icon' => 'mail',
                'variant' => 'conversation',
                'singular' => 'Conversation',
                'statuses' => ['new' => 'Unread', 'open' => 'Open', 'pending' => 'Pending', 'closed' => 'Resolved'],
                'tabs' => ['all' => 'All Channels', 'new' => 'Unread', 'open' => 'Open', 'pending' => 'Pending', 'closed' => 'Resolved'],
            ],
            'chat-24-7' => [
                'title' => 'Chat 24/7',
                'subtitle' => 'Live chat with website visitors and customers in real time.',
                'icon' => 'message',
                'variant' => 'conversation',
                'singular' => 'Chat',
                'statuses' => ['new' => 'Active', 'open' => 'Active', 'pending' => 'Waiting', 'closed' => 'Closed'],
                'tabs' => ['all' => 'Active', 'pending' => 'Waiting', 'closed' => 'Closed'],
            ],
            'whatsapp' => [
                'title' => 'WhatsApp',
                'subtitle' => 'Manage WhatsApp conversations, automate responses and engage with customers instantly.',
                'icon' => 'message',
                'variant' => 'conversation',
                'singular' => 'WhatsApp conversation',
                'statuses' => ['new' => 'Open', 'open' => 'Open', 'pending' => 'Pending', 'closed' => 'Resolved'],
                'tabs' => ['all' => 'All Conversations', 'new' => 'Unassigned', 'open' => 'Mine', 'closed' => 'Resolved'],
            ],
            'email' => [
                'title' => 'Email',
                'subtitle' => 'Manage customer email conversations, assignments, approvals and follow-ups from the shared cPanel.',
                'icon' => 'mail',
                'variant' => 'conversation',
                'singular' => 'Email',
                'statuses' => ['new' => 'New', 'open' => 'Open', 'pending' => 'Approval Required', 'closed' => 'Resolved'],
                'tabs' => ['all' => 'All Emails', 'new' => 'New', 'open' => 'Open', 'pending' => 'Approval Required', 'closed' => 'Resolved'],
            ],
            'email-templates' => [
                'title' => 'Email Templates',
                'subtitle' => 'Create, manage and track email templates for all communication needs.',
                'icon' => 'mail',
                'variant' => 'records',
                'singular' => 'Template',
                'create_label' => 'Create Template',
                'statuses' => ['active' => 'Active', 'draft' => 'Draft', 'pending_approval' => 'Approval Required', 'archived' => 'Archived'],
                'tabs' => ['all' => 'All Templates', 'active' => 'Active', 'draft' => 'Custom Templates', 'pending_approval' => 'Approval Required', 'archived' => 'Archived'],
            ],
            'approval-center' => [
                'title' => 'Approval Center',
                'subtitle' => 'Review, approve or reject requests and track all approval workflows.',
                'icon' => 'check',
                'variant' => 'records',
                'singular' => 'Approval request',
                'create_label' => 'New Approval Request',
                'statuses' => ['pending' => 'Pending', 'in-progress' => 'In Progress', 'approved' => 'Approved', 'rejected' => 'Rejected', 'escalated' => 'Escalated', 'cancelled' => 'Cancelled'],
                'tabs' => ['all' => 'All Requests', 'pending' => 'Pending', 'in-progress' => 'In Progress', 'approved' => 'Approved', 'rejected' => 'Rejected', 'escalated' => 'Escalated', 'cancelled' => 'Cancelled'],
            ],
            'action-follow-ups' => [
                'title' => 'Action / Follow-ups',
                'subtitle' => 'Manage tasks, follow-ups and commitments across all communications and operations.',
                'icon' => 'check',
                'variant' => 'records',
                'singular' => 'Action',
                'create_label' => 'New Action',
                'statuses' => ['pending' => 'Pending', 'in-progress' => 'In Progress', 'completed' => 'Completed', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'],
                'tabs' => ['all' => 'All Actions', 'pending' => 'Pending', 'in-progress' => 'In Progress', 'overdue' => 'Overdue', 'completed' => 'Completed', 'cancelled' => 'Cancelled'],
            ],
            'alerts-notifications' => [
                'title' => 'Alerts & Notifications',
                'subtitle' => 'Monitor critical events and stay informed in real-time.',
                'icon' => 'bell',
                'variant' => 'records',
                'singular' => 'Alert',
                'create_label' => 'Create Alert',
                'statuses' => ['unread' => 'Unread', 'in-progress' => 'In Progress', 'acknowledged' => 'Acknowledged', 'escalated' => 'Escalated', 'resolved' => 'Resolved'],
                'tabs' => ['all' => 'All Alerts', 'unread' => 'Unread', 'in-progress' => 'In Progress', 'acknowledged' => 'Acknowledged', 'escalated' => 'Escalated', 'resolved' => 'Resolved'],
            ],
            'communication-reports' => [
                'title' => 'Communication Reports',
                'subtitle' => 'Track performance and effectiveness of all communication channels and activities.',
                'icon' => 'chart',
                'variant' => 'reports',
                'singular' => 'Report',
                'statuses' => [],
                'tabs' => ['overview' => 'Overview', 'channels' => 'Channels', 'agents' => 'Agents', 'customers' => 'Customers', 'franchise' => 'Franchise & Stores', 'orders' => 'Orders', 'approvals' => 'Approvals', 'followups' => 'Follow-ups', 'sla' => 'SLA & Performance', 'trends' => 'Trends', 'audit' => 'Audit'],
            ],
            'communication-history' => [
                'title' => 'Communication History / Audit Log',
                'subtitle' => 'Complete audit trail of all communication activities, changes, actions and events.',
                'icon' => 'file-text',
                'variant' => 'history',
                'singular' => 'Activity',
                'statuses' => [],
                'tabs' => ['all' => 'All Activities', 'messages' => 'Messages', 'chats' => 'Chats', 'whatsapp' => 'WhatsApp', 'emails' => 'Emails', 'approvals' => 'Approvals', 'followups' => 'Follow-ups', 'system' => 'System Events', 'changes' => 'Data Changes', 'logins' => 'Logins', 'exports' => 'Exports'],
            ],
        ];

        return $configs[$section] ?? null;
    }
}
