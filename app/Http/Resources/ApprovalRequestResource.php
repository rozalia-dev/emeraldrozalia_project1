<?php

namespace App\Http\Resources;

use App\Models\Approval;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ApprovalRequestResource extends JsonResource
{
    private const PUBLIC_METADATA_KEYS = ['category', 'source', 'channel', 'tags', 'context'];

    public function toArray(Request $request): array
    {
        /** @var Approval $approval */
        $approval = $this->resource;

        return [
            'uuid' => $approval->uuid,
            'reference' => $approval->reference,
            'type' => $approval->request_type,
            'title' => $approval->title,
            'description' => $approval->description,
            'status' => $approval->status,
            'priority' => $approval->priority,
            'requested_by' => $this->person($approval->relationLoaded('requestedBy') ? $approval->getRelation('requestedBy') : null, $approval->requester_name),
            'approver' => $this->person($approval->relationLoaded('approver') ? $approval->getRelation('approver') : null, $approval->approver_name),
            'decided_by' => $this->person($approval->relationLoaded('decidedBy') ? $approval->getRelation('decidedBy') : null, null),
            'entity' => $this->entity($approval),
            'source' => $approval->source,
            'due_at' => $approval->due_at?->toIso8601String(),
            'record_date' => $approval->record_date?->toDateString(),
            'decision' => [
                'note' => $approval->decision_note,
                'decided_at' => $approval->decided_at?->toIso8601String(),
                'decided_by_uuid' => $approval->decidedBy?->public_uuid,
            ],
            'metadata' => Arr::only((array) $approval->metadata, self::PUBLIC_METADATA_KEYS),
            'version' => (int) $approval->version,
            'correlation_id' => $approval->correlation_id,
            'created_at' => $approval->created_at?->toIso8601String(),
            'updated_at' => $approval->updated_at?->toIso8601String(),
        ];
    }

    private function person(?User $user, ?string $fallback): ?array
    {
        if ($user) {
            return [
                'uuid' => $user->public_uuid,
                'name' => $fallback ?: $user->name,
            ];
        }

        return filled($fallback) ? ['uuid' => null, 'name' => $fallback] : null;
    }

    private function entity(Approval $approval): ?array
    {
        if (! $approval->entity && ! $approval->entity_type && ! $approval->entity_uuid) {
            return null;
        }

        return [
            'label' => $approval->entity,
            'type' => $approval->entity_type,
            'uuid' => $approval->entity_uuid,
        ];
    }
}
