<?php

namespace App\Services;

use App\Events\CommunicationTemplateChanged;
use App\Models\CommunicationTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommunicationTemplateService
{
    public const STATUSES = ['draft', 'pending_approval', 'active', 'archived'];

    public const ACTIONS = ['activate', 'archive', 'duplicate', 'restore', 'submit_for_approval'];

    public function create(array $attributes, ?string $idempotencyKey = null): CommunicationTemplate
    {
        $payload = $this->normalize($attributes);
        $requestHash = $this->requestHash($payload);
        $this->validateIdempotencyKey($idempotencyKey);
        $template = null;
        $replayed = false;

        try {
            DB::transaction(function () use (&$template, &$replayed, $payload, $idempotencyKey, $requestHash): void {
                if ($idempotencyKey) {
                    $existing = CommunicationTemplate::withoutGlobalScopes()
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        $this->assertIdempotentReplay($existing, $requestHash);
                        $template = $existing;
                        $replayed = true;

                        return;
                    }
                }

                $template = new CommunicationTemplate($this->templateAttributes($payload));
                $template->idempotency_key = $idempotencyKey;
                $template->request_hash = $requestHash;
                $template->correlation_id = $this->correlationId();
                $template->created_by = auth()->id();
                $template->updated_by = auth()->id();
                $this->applyPublicationTimestamps($template, $payload['status']);
                $template->save();

                AuditTrail::record(
                    'communication.email-template.created',
                    $template,
                    null,
                    $this->auditState($template),
                );
            });
        } catch (QueryException $exception) {
            if (! $idempotencyKey) {
                throw $exception;
            }

            $existing = CommunicationTemplate::withoutGlobalScopes()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if (! $existing) {
                throw $exception;
            }

            $this->assertIdempotentReplay($existing, $requestHash);
            $template = $existing;
            $replayed = true;
        }

        if (! $replayed && $template) {
            $this->dispatchChanged($template, 'created');
        }

        return $template;
    }

    public function update(CommunicationTemplate $template, array $attributes): CommunicationTemplate
    {
        $payload = $this->normalize($attributes, false);
        $expectedVersion = $payload['expected_version'] ?? null;
        unset($payload['expected_version']);
        $updated = null;

        DB::transaction(function () use (&$updated, $template, $payload, $expectedVersion): void {
            $locked = CommunicationTemplate::withoutGlobalScopes()
                ->whereKey($template->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertTenant($locked);

            if ($expectedVersion !== null && (int) $locked->version !== (int) $expectedVersion) {
                abort(409, 'The email template changed before this update was saved. Refresh and try again.');
            }

            $before = $this->auditState($locked);
            $locked->fill($this->templateAttributes($payload, false));
            $locked->version = (int) $locked->version + 1;
            $locked->updated_by = auth()->id();
            if (array_key_exists('status', $payload)) {
                $this->applyPublicationTimestamps($locked, $payload['status']);
            }
            $locked->save();

            AuditTrail::record(
                'communication.email-template.updated',
                $locked,
                $before,
                $this->auditState($locked),
            );
            $updated = $locked;
        });

        $this->dispatchChanged($updated, 'updated');

        return $updated;
    }

    public function transition(CommunicationTemplate $template, string $action): CommunicationTemplate
    {
        abort_unless(in_array($action, self::ACTIONS, true) && $action !== 'duplicate', 404);

        $targetStatus = match ($action) {
            'activate' => 'active',
            'archive' => 'archived',
            'restore' => 'draft',
            'submit_for_approval' => 'pending_approval',
        };

        return $this->update($template, ['status' => $targetStatus]);
    }

    public function duplicate(CommunicationTemplate $template): CommunicationTemplate
    {
        $copy = null;

        DB::transaction(function () use (&$copy, $template): void {
            $source = CommunicationTemplate::withoutGlobalScopes()
                ->whereKey($template->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertTenant($source);

            $copy = $source->replicate([
                'uuid',
                'idempotency_key',
                'request_hash',
                'correlation_id',
                'published_at',
                'archived_at',
                'version',
                'created_by',
                'updated_by',
            ]);
            $copy->uuid = (string) Str::uuid();
            $copy->name = 'Copy of '.$source->name;
            $copy->status = 'draft';
            $copy->version = 1;
            $copy->created_by = auth()->id();
            $copy->updated_by = auth()->id();
            $copy->correlation_id = $this->correlationId();
            $copy->save();

            AuditTrail::record(
                'communication.email-template.duplicated',
                $copy,
                null,
                $this->auditState($copy),
            );
        });

        $this->dispatchChanged($copy, 'duplicated');

        return $copy;
    }

    public function delete(CommunicationTemplate $template): void
    {
        DB::transaction(function () use ($template): void {
            $locked = CommunicationTemplate::withTrashed()
                ->withoutGlobalScopes()
                ->whereKey($template->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertTenant($locked);
            $before = $this->auditState($locked);
            $locked->delete();

            AuditTrail::record('communication.email-template.deleted', $locked, $before, null);
        });

        $this->dispatchChanged($template, 'deleted');
    }

    public function auditState(CommunicationTemplate $template): array
    {
        $variables = is_array($template->variables) ? $template->variables : [];

        return [
            'uuid' => $template->uuid,
            'channel' => $template->channel,
            'name' => $template->name,
            'subject' => $template->subject,
            'status' => $template->status,
            'version' => (int) $template->version,
            'body_sha256' => hash('sha256', (string) $template->body),
            'variable_keys' => array_values(array_keys($variables)),
        ];
    }

    private function normalize(array $attributes, bool $creating = true): array
    {
        $payload = $attributes;

        if (array_key_exists('title', $payload) && ! array_key_exists('name', $payload)) {
            $payload['name'] = $payload['title'];
        }
        unset($payload['title']);

        if ($creating) {
            $payload['channel'] ??= 'email';
            $payload['status'] ??= 'draft';
        }

        if (array_key_exists('channel', $payload)) {
            $payload['channel'] = strtolower(trim((string) $payload['channel']));
        }
        if (array_key_exists('name', $payload)) {
            $payload['name'] = trim((string) $payload['name']);
        }
        if (array_key_exists('subject', $payload)) {
            $payload['subject'] = trim((string) $payload['subject']);
        }
        if (array_key_exists('status', $payload)) {
            $payload['status'] = strtolower(trim((string) $payload['status']));
        }
        if (array_key_exists('variables', $payload) && is_array($payload['variables'])) {
            ksort($payload['variables']);
        }

        abort_unless(($payload['channel'] ?? 'email') === 'email', 422, 'Email templates must use the email channel.');
        abort_unless(isset($payload['name']) && $payload['name'] !== '' || ! $creating, 422, 'A template name is required.');
        abort_unless(isset($payload['status']) && in_array($payload['status'], self::STATUSES, true) || ! $creating, 422, 'The template status is invalid.');

        return $payload;
    }

    private function templateAttributes(array $payload, bool $creating = true): array
    {
        $attributes = [];
        foreach (['name', 'subject', 'body', 'channel', 'status', 'variables'] as $key) {
            if (array_key_exists($key, $payload)) {
                $attributes[$key] = $payload[$key];
            }
        }

        if ($creating) {
            $attributes['channel'] ??= 'email';
            $attributes['status'] ??= 'draft';
            $attributes['variables'] ??= [];
        }

        return $attributes;
    }

    private function applyPublicationTimestamps(CommunicationTemplate $template, string $status): void
    {
        $template->published_at = $status === 'active' ? ($template->published_at ?: now()) : null;
        $template->archived_at = $status === 'archived' ? ($template->archived_at ?: now()) : null;
    }

    private function dispatchChanged(?CommunicationTemplate $template, string $action): void
    {
        if (! $template) {
            return;
        }

        $correlationId = (string) ($template->correlation_id ?: $this->correlationId());
        // The mutation transaction has completed before this callback is
        // reached. The event itself also carries Laravel's after-commit
        // contract for any future in-transaction dispatches.
        event(new CommunicationTemplateChanged($template, $action, $correlationId));
    }

    private function assertIdempotencyKey(?string $idempotencyKey): void
    {
        if ($idempotencyKey === null || $idempotencyKey === '') {
            return;
        }

        abort_unless(
            preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $idempotencyKey) === 1,
            422,
            'Idempotency-Key must contain only letters, numbers, dots, underscores, colons or hyphens.',
        );
    }

    private function assertIdempotentReplay(CommunicationTemplate $template, string $requestHash): void
    {
        if (! hash_equals((string) $template->request_hash, $requestHash)) {
            abort(409, 'The Idempotency-Key was already used for a different template.');
        }
    }

    private function requestHash(array $payload): string
    {
        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function correlationId(): string
    {
        $candidate = app()->bound('request') ? request()->attributes->get('correlation_id') : null;

        return is_string($candidate) && Str::isUuid($candidate) ? $candidate : (string) Str::uuid();
    }

    private function assertTenant(CommunicationTemplate $template): void
    {
        $companyId = session('company_id');
        if ($companyId && (int) $template->company_id !== (int) $companyId) {
            abort(404);
        }
    }
}
