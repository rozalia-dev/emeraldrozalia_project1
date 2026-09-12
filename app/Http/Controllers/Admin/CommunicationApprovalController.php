<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApprovalDecisionRequest;
use App\Http\Requests\ApprovalRequestIndexRequest;
use App\Http\Requests\ApprovalRequestRequest;
use App\Http\Resources\ApprovalRequestResource;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Services\ApprovalRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunicationApprovalController extends Controller
{
    public function __construct(private readonly ApprovalRequestService $service)
    {
    }

    public function store(ApprovalRequestRequest $request): RedirectResponse
    {
        Gate::authorize('create', Approval::class);
        $this->service->create($request->approvalPayload(), $this->idempotencyKey($request));

        return back()->with('success', 'Approval request created.');
    }

    public function update(ApprovalRequestRequest $request, Approval $approval): RedirectResponse
    {
        Gate::authorize('update', $approval);
        $this->service->update($approval, $request->approvalPayload());

        return back()->with('success', 'Approval request updated.');
    }

    public function destroy(Approval $approval): RedirectResponse
    {
        Gate::authorize('delete', $approval);
        $this->service->delete($approval);

        return back()->with('success', 'Approval request deleted.');
    }

    public function action(ApprovalDecisionRequest $request, Approval $approval, string $action): RedirectResponse
    {
        $this->assertAction($action);
        Gate::authorize('decide', $approval);
        $this->service->decide($approval, $action, $request->validated());

        return back()->with('success', ucfirst($action).' completed.');
    }

    public function exportAudit(Approval $approval): StreamedResponse
    {
        Gate::authorize('view', $approval);
        $logs = $this->auditQuery($approval)->limit(10000)->get();

        return response()->streamDownload(function () use ($logs): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Audit UUID', 'Actor UUID', 'Action', 'Subject UUID', 'Request ID', 'IP Address', 'Before', 'After', 'Created']);
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->uuid,
                    $log->actor_uuid,
                    $log->action,
                    $log->subject_uuid,
                    $log->request_id,
                    $log->ip_address,
                    json_encode($this->redactSnapshot($log->before), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    json_encode($this->redactSnapshot($log->after), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    optional($log->created_at)->toDateTimeString(),
                ]);
            }
            fclose($handle);
        }, 'approval-audit-'.$approval->uuid.'.csv', ['Content-Type' => 'text/csv']);
    }

    public function apiIndex(ApprovalRequestIndexRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Approval::class);
        $query = $this->approvalQuery();
        $this->applyFilters($query, $request->validated());
        $perPage = min(50, max(1, (int) $request->input('per_page', 20)));

        return ApprovalRequestResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function apiShow(Approval $approval): ApprovalRequestResource
    {
        Gate::authorize('view', $approval);

        return new ApprovalRequestResource($approval->load(['requestedBy', 'approver', 'decidedBy']));
    }

    public function apiStore(ApprovalRequestRequest $request): JsonResponse
    {
        Gate::authorize('create', Approval::class);
        $approval = $this->service->create($request->approvalPayload(), $this->idempotencyKey($request));
        $approval->load(['requestedBy', 'approver', 'decidedBy']);

        return (new ApprovalRequestResource($approval))
            ->response($request)
            ->setStatusCode($approval->wasRecentlyCreated ? 201 : 200);
    }

    public function apiUpdate(ApprovalRequestRequest $request, Approval $approval): JsonResponse
    {
        Gate::authorize('update', $approval);
        $updated = $this->service->update($approval, $request->approvalPayload());
        $updated->load(['requestedBy', 'approver', 'decidedBy']);

        return (new ApprovalRequestResource($updated))->response($request);
    }

    public function apiDelete(Approval $approval): JsonResponse
    {
        Gate::authorize('delete', $approval);
        $this->service->delete($approval);

        return response()->json(null, 204);
    }

    public function apiAction(ApprovalDecisionRequest $request, Approval $approval, string $action): JsonResponse
    {
        $this->assertAction($action);
        Gate::authorize('decide', $approval);
        $decided = $this->service->decide($approval, $action, $request->validated());
        $decided->load(['requestedBy', 'approver', 'decidedBy']);

        return (new ApprovalRequestResource($decided))->response($request);
    }

    public function audit(Approval $approval): JsonResponse
    {
        Gate::authorize('view', $approval);
        $logs = $this->auditQuery($approval)->paginate(50)->withQueryString();

        return response()->json([
            'data' => collect($logs->items())->map(fn (AuditLog $log): array => $this->auditItem($log))->values(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'from' => $logs->firstItem(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'to' => $logs->lastItem(),
                'total' => $logs->total(),
            ],
        ]);
    }

    private function approvalQuery(): Builder
    {
        return Approval::query()
            ->forCurrentCompany()
            ->with(['requestedBy', 'approver', 'decidedBy'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('uuid', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('entity', 'like', $like)
                    ->orWhere('source', 'like', $like)
                    ->orWhereHas('requestedBy', fn (Builder $user) => $user
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('public_uuid', 'like', $like))
                    ->orWhereHas('approver', fn (Builder $user) => $user
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('public_uuid', 'like', $like));
            });
        }

        $type = $filters['request_type'] ?? $filters['type'] ?? null;
        if (filled($type)) {
            $query->where('request_type', $type);
        }
        foreach (['status', 'priority'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }
        if (filled($filters['requested_by'] ?? null)) {
            $like = '%'.trim((string) $filters['requested_by']).'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('requester_name', 'like', $like)
                    ->orWhereHas('requestedBy', fn (Builder $user) => $user->where('name', 'like', $like));
            });
        }
        if (filled($filters['approver'] ?? null)) {
            $like = '%'.trim((string) $filters['approver']).'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('approver_name', 'like', $like)
                    ->orWhereHas('approver', fn (Builder $user) => $user->where('name', 'like', $like));
            });
        }
        if (filled($filters['date_from'] ?? null)) {
            $query->where('record_date', '>=', Carbon::parse($filters['date_from'])->toDateString());
        }
        if (filled($filters['date_to'] ?? null)) {
            $query->where('record_date', '<=', Carbon::parse($filters['date_to'])->toDateString());
        }
    }

    private function auditQuery(Approval $approval): Builder
    {
        return AuditLog::query()
            ->where('subject_uuid', $approval->uuid)
            ->latest('created_at');
    }

    private function auditItem(AuditLog $log): array
    {
        return [
            'uuid' => $log->uuid,
            'actor_uuid' => $log->actor_uuid,
            'action' => $log->action,
            'subject_uuid' => $log->subject_uuid,
            'request_id' => $log->request_id,
            'ip_address' => $log->ip_address,
            'before' => $this->redactSnapshot($log->before),
            'after' => $this->redactSnapshot($log->after),
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    private function redactSnapshot(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $sensitive = ['body', 'content', 'description', 'email', 'message', 'password', 'phone', 'secret', 'text', 'token'];

        return collect($value)
            ->reject(fn ($item, $key): bool => in_array(strtolower((string) $key), $sensitive, true))
            ->map(fn ($item) => $this->redactSnapshot($item))
            ->all();
    }

    private function idempotencyKey(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    private function assertAction(string $action): void
    {
        abort_unless(in_array($action, ApprovalRequestService::ACTIONS, true), 404);
    }
}
