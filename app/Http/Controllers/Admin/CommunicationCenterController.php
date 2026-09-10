<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRecord;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\AuditTrail;
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
        'email-templates',
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

        if (in_array($section, self::CONVERSATION_SECTIONS, true)) {
            $query = $this->conversationQuery($section);
            $this->applyConversationFilters($query, $request);
            $conversations = $query->paginate($section === 'communication-center' ? 8 : 10)->withQueryString();

            $selectedId = (int) $request->query('conversation', 0);
            $selected = $selectedId > 0
                ? $this->conversationQuery($section)->whereKey($selectedId)->first()
                : $conversations->getCollection()->first();

            return view('admin.communication-center.dashboard', $common + [
                'metrics' => $this->conversationMetrics($section),
                'report' => $section === 'communication-center' ? $this->reportData() : $this->conversationSideData($section),
                'conversations' => $conversations,
                'selected' => $selected,
                'records' => null,
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

        if ($action === 'duplicate' && $section === 'email-templates') {
            // A public UUID is unique across admin records. Never carry it to
            // a duplicated template; the model event will generate a fresh one.
            $copy = $record->replicate(['reference', 'public_uuid']);
            $copy->title = 'Copy of '.$record->title;
            $copy->reference = null;
            $copy->record_date = now()->toDateString();
            $copy->user_id = auth()->id();
            $copy->save();
            $copy->update(['reference' => $this->referenceFor($section, $copy->id)]);
            AuditTrail::record('communication.email-template.duplicated', $copy, null, $copy->fresh()->toArray());

            return back()->with('success', 'Template duplicated.');
        }

        $nextStatus = match ([$section, $action]) {
            ['approval-center', 'approve'] => 'approved',
            ['approval-center', 'reject'] => 'rejected',
            ['action-follow-ups', 'complete'] => 'completed',
            ['action-follow-ups', 'reopen'] => 'pending',
            ['alerts-notifications', 'acknowledge'] => 'acknowledged',
            ['alerts-notifications', 'resolve'] => 'resolved',
            ['email-templates', 'archive'] => 'archived',
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

        if (in_array($section, self::CONVERSATION_SECTIONS, true)) {
            $query = $this->conversationQuery($section);
            $this->applyConversationFilters($query, $request);
            $rows = $query->limit(10000)->get();

