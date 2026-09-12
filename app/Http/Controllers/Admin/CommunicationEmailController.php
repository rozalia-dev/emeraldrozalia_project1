<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommunicationConversationUpdateRequest;
use App\Http\Requests\CommunicationEmailIndexRequest;
use App\Http\Requests\CommunicationReplyRequest;
use App\Http\Resources\CommunicationConversationMessageResource;
use App\Http\Resources\CommunicationConversationResource;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\CommunicationCenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunicationEmailController extends Controller
{
    public function __construct(private readonly CommunicationCenter $service)
    {
    }

    public function index(CommunicationEmailIndexRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Conversation::class);

        $query = Conversation::query()
            ->forCurrentCompany()
            ->where('channel', 'email')
            ->with(['assignee', 'customer', 'order'])
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $this->applyFilters($query, $request->validated());
        $perPage = min(50, max(1, (int) $request->input('per_page', 20)));

        return CommunicationConversationResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(Conversation $conversation): CommunicationConversationResource
    {
        $this->assertEmail($conversation);
        Gate::authorize('view', $conversation);

        return new CommunicationConversationResource($conversation->load([
            'messages' => fn ($messages) => $messages->oldest('id'),
            'assignee',
            'customer',
            'order',
        ]));
    }

    public function update(CommunicationConversationUpdateRequest $request, Conversation $conversation): JsonResponse
    {
        $this->assertEmail($conversation);
        Gate::authorize('update', $conversation);
        $updated = $this->service->updateConversation($conversation, $request->validated());

        return (new CommunicationConversationResource($updated->load(['assignee', 'customer', 'order'])))
            ->response($request);
    }

    public function reply(CommunicationReplyRequest $request, Conversation $conversation): JsonResponse
    {
        $this->assertEmail($conversation);
        Gate::authorize('update', $conversation);
        $idempotencyKey = $request->header('Idempotency-Key');
        $message = $this->service->sendReply(
            $conversation,
            $request->validated('body'),
            is_string($idempotencyKey) ? $idempotencyKey : null,
        );

        return (new CommunicationConversationMessageResource($message->load('conversation')))
            ->response($request);
    }

    public function audit(Conversation $conversation): JsonResponse
    {
        $this->assertEmail($conversation);
        Gate::authorize('view', $conversation);
        $logs = $this->auditQuery($conversation)->paginate(50)->withQueryString();

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

    public function webAction(Request $request, Conversation $conversation, string $action): RedirectResponse
    {
        $this->assertEmail($conversation);
        Gate::authorize('update', $conversation);

        $payload = match ($action) {
            'resolve' => ['status' => 'closed'],
            'reopen' => ['status' => 'open'],
            'escalate' => ['priority' => 'urgent'],
            default => abort(404),
        };
        $this->service->updateConversation($conversation, $payload);

        return back()->with('success', 'Email conversation action completed.');
    }

    public function exportAudit(Conversation $conversation): StreamedResponse
    {
        $this->assertEmail($conversation);
        Gate::authorize('view', $conversation);
        $logs = $this->auditQuery($conversation)->limit(10000)->get();

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
        }, 'email-audit-'.$conversation->uuid.'.csv', ['Content-Type' => 'text/csv']);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $this->applySearch($query, $search);
        }

        foreach (['customer', 'order', 'uid'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value === '') {
                continue;
            }

            $this->applyFieldFilter($query, $field, $value);
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }
        if (filled($filters['priority'] ?? null)) {
            $query->where('priority', $filters['priority']);
        }
        if (filled($filters['date_from'] ?? null)) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        if (filled($filters['date_to'] ?? null)) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }
    }

    private function applySearch(Builder $query, string $search): void
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
            $this->orWhereMetadata($builder, $like);
        });
    }

    private function applyFieldFilter(Builder $query, string $field, string $value): void
    {
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
                    ->orWhere('public_uuid', 'like', $like))
                    ->orWhere(function (Builder $metadata) use ($like): void {
                        $this->orWhereMetadata($metadata, $like);
                    });
            } else {
                $builder->where('uuid', 'like', $like)
                    ->orWhereHas('messages', fn (Builder $messages) => $messages->where('uuid', 'like', $like));
                $this->orWhereMetadata($builder, $like);
            }
        });
    }

    private function orWhereMetadata(Builder $query, string $like): void
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

    private function auditQuery(Conversation $conversation): Builder
    {
        $messageUuids = $conversation->messages()->pluck('uuid');

        return AuditLog::query()
            ->whereIn('subject_uuid', $messageUuids->prepend($conversation->uuid))
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

        $sensitive = ['body', 'content', 'email', 'message', 'password', 'phone', 'secret', 'text', 'token'];

        return collect($value)
            ->reject(fn ($item, $key): bool => in_array(strtolower((string) $key), $sensitive, true))
            ->map(fn ($item) => $this->redactSnapshot($item))
            ->all();
    }

    private function assertEmail(Conversation $conversation): void
    {
        abort_unless($conversation->channel === 'email', 404);
    }
}
