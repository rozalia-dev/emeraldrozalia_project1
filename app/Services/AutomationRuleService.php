<?php

namespace App\Services;

use App\Jobs\RunAutomationRules;
use App\Models\{AutomationRule, AutomationRun};
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class AutomationRuleService
{
    public function create(array $attributes): AutomationRule
    {
        $event = strtolower(trim((string) ($attributes['event'] ?? '')));
        abort_unless(in_array($event, AutomationRule::EVENTS, true), 422, 'The automation event is not supported by Project 1.');

        $actions = $this->normalizeActions($attributes['actions'] ?? []);
        abort_unless($actions !== [], 422, 'At least one automation action is required.');

        $conditions = $attributes['conditions'] ?? [];
        if (is_string($conditions)) {
            $conditions = json_decode($conditions, true);
        }
        abort_unless(is_array($conditions), 422, 'Automation conditions must be a JSON object.');

        $rule = AutomationRule::create([
            'company_id' => $attributes['company_id'] ?? session('company_id'),
            'name' => trim((string) ($attributes['name'] ?? '')),
            'event' => $event,
            'conditions' => $conditions,
            'actions' => $actions,
            'enabled' => (bool) ($attributes['enabled'] ?? false),
        ]);

        AuditTrail::record('settings.automation.created', $rule, null, $rule->toArray());

        return $rule;
    }

    public function toggle(AutomationRule $rule): AutomationRule
    {
        $companyId = session('company_id');
        abort_if($companyId && $rule->company_id && (int) $rule->company_id !== (int) $companyId, 404);

        $before = $rule->toArray();
        $rule->update(['enabled' => ! $rule->enabled]);
        AuditTrail::record('settings.automation.toggled', $rule, $before, $rule->fresh()->toArray());

        return $rule->fresh();
    }

    public function queue(string $event, array $payload, string $eventKey, ?int $companyId = null): void
    {
        if (! in_array($event, AutomationRule::EVENTS, true)) {
            return;
        }

        $companyId ??= isset($payload['company_id']) ? (int) $payload['company_id'] : null;
        RunAutomationRules::dispatch($event, $payload, $eventKey, $companyId)->afterCommit();
    }

    public function run(string $event, array $payload, string $eventKey, ?int $companyId = null): Collection
    {
        if (! in_array($event, AutomationRule::EVENTS, true)) {
            return collect();
        }

        $companyId ??= isset($payload['company_id']) ? (int) $payload['company_id'] : null;
        $rules = AutomationRule::withoutGlobalScopes()
            ->where('event', $event)
            ->where('enabled', true)
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId), fn ($query) => $query->whereNull('company_id'))
            ->orderBy('id')
            ->get();

        return $rules->map(fn (AutomationRule $rule): AutomationRun => $this->runRule($rule, $event, $payload, $eventKey, $companyId));
    }

    /** @return array<int, string> */
    public function normalizeActions(array|string $actions): array
    {
        $items = is_array($actions) ? $actions : explode(',', $actions);
        $normalized = [];

        foreach ($items as $item) {
            $raw = trim((string) $item);
            if ($raw === '') {
                continue;
            }

            $key = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', $raw)));
            $normalized[] = match ($key) {
                'create task', 'create action', 'create follow up', 'create follow-up', 'task', 'follow up', 'follow-up' => 'create_action',
                'create alert', 'create notification', 'alert', 'notification' => 'create_alert',
                'audit', 'record audit', 'log audit' => 'audit',
                default => 'unsupported:'.$key,
            };
        }

        return array_values(array_unique($normalized));
    }

    private function runRule(AutomationRule $rule, string $event, array $payload, string $eventKey, ?int $companyId): AutomationRun
    {
        $idempotencyKey = hash('sha256', $rule->uuid.'|'.$event.'|'.$eventKey);
        $run = null;

        try {
            DB::transaction(function () use (&$run, $rule, $event, $payload, $eventKey, $idempotencyKey, $companyId): void {
                $run = AutomationRun::withoutGlobalScopes()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($run) {
                    return;
                }

                $run = AutomationRun::create([
                    'company_id' => $companyId,
                    'automation_rule_id' => $rule->getKey(),
                    'event' => $event,
                    'event_key' => $eventKey,
                    'idempotency_key' => $idempotencyKey,
                    'status' => 'running',
                    'payload' => $payload,
                    'started_at' => now(),
                ]);
            });
        } catch (QueryException) {
            $run = AutomationRun::withoutGlobalScopes()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }

        if (in_array($run->status, ['completed', 'partial', 'skipped'], true)) {
            return $run;
        }

        try {
            if (! $this->matches($rule, $payload)) {
                return $this->finish($rule, $run, 'skipped', ['reason' => 'conditions_not_met']);
            }

            $result = ['actions' => [], 'unsupported' => []];
            foreach ((array) $rule->actions as $action) {
                $result['actions'][] = $this->executeAction($action, $rule, $run, $event, $payload, $companyId, $result['unsupported']);
            }

            $status = $result['unsupported'] === [] ? 'completed' : 'partial';

            return $this->finish($rule, $run, $status, $result);
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'error' => Str::limit($exception->getMessage(), 500, ''),
                'completed_at' => now(),
            ])->save();
            AutomationRule::withoutGlobalScopes()->whereKey($rule->getKey())->update([
                'last_run_at' => now(),
                'last_run_status' => 'failed',
            ]);
            AuditTrail::record('settings.automation.failed', $run->fresh(), null, [
                'rule_uuid' => $rule->uuid,
                'event' => $event,
                'exception' => get_class($exception),
            ]);
            throw $exception;
        }
    }

    private function finish(AutomationRule $rule, AutomationRun $run, string $status, array $result): AutomationRun
    {
        $run->forceFill([
            'status' => $status,
            'result' => $result,
            'completed_at' => now(),
        ])->save();
        AutomationRule::withoutGlobalScopes()->whereKey($rule->getKey())->update([
            'last_run_at' => now(),
            'last_run_status' => $status,
        ]);
        AuditTrail::record('settings.automation.'.$status, $run->fresh(), null, [
            'rule_uuid' => $rule->uuid,
            'result' => $result,
        ]);

        return $run->fresh();
    }

    private function matches(AutomationRule $rule, array $payload): bool
    {
        foreach ((array) $rule->conditions as $key => $expected) {
            if (data_get($payload, $key) != $expected) {
                return false;
            }
        }

        return true;
    }

    private function executeAction(string $action, AutomationRule $rule, AutomationRun $run, string $event, array $payload, ?int $companyId, array &$unsupported): string
    {
        return match ($action) {
            'create_action' => $this->createAction($rule, $run, $event, $payload, $companyId),
            'create_alert' => $this->createAlert($rule, $run, $event, $payload, $companyId),
            'audit' => $this->recordAudit($rule, $run, $event, $payload),
            default => $this->unsupported($action, $unsupported),
        };
    }

    private function createAction(AutomationRule $rule, AutomationRun $run, string $event, array $payload, ?int $companyId): string
    {
        $item = app(CommunicationWorkItemService::class)->create('action-follow-ups', [
            'company_id' => $companyId,
            'title' => Str::limit((string) ($payload['title'] ?? 'Automated follow-up: '.$rule->name), 180, ''),
            'description' => $payload['description'] ?? ('Created by automation for '.$event.'.'),
            'source' => 'Automation: '.$rule->name,
            'entity' => $payload['entity_uuid'] ?? $payload['quote_uuid'] ?? $payload['order_uuid'] ?? null,
            'status' => 'pending',
            'priority' => $payload['priority'] ?? 'normal',
            'idempotency_key' => 'automation-'.$run->uuid,
        ]);

        return 'action:'.$item->uuid;
    }

    private function createAlert(AutomationRule $rule, AutomationRun $run, string $event, array $payload, ?int $companyId): string
    {
        $item = app(CommunicationWorkItemService::class)->create('alerts-notifications', [
            'company_id' => $companyId,
            'title' => Str::limit((string) ($payload['title'] ?? 'Automated alert: '.$rule->name), 180, ''),
            'description' => $payload['description'] ?? ('Created by automation for '.$event.'.'),
            'source' => 'Automation: '.$rule->name,
            'entity' => $payload['entity_uuid'] ?? $payload['quote_uuid'] ?? $payload['order_uuid'] ?? null,
            'status' => 'unread',
            'priority' => $payload['priority'] ?? 'normal',
            'severity' => $payload['severity'] ?? 'low',
            'idempotency_key' => 'automation-'.$run->uuid,
        ]);

        return 'alert:'.$item->uuid;
    }

    private function recordAudit(AutomationRule $rule, AutomationRun $run, string $event, array $payload): string
    {
        AuditTrail::record('settings.automation.action_audit', $run, null, [
            'rule_uuid' => $rule->uuid,
            'event' => $event,
            'payload' => $payload,
        ]);

        return 'audit';
    }

    private function unsupported(string $action, array &$unsupported): string
    {
        $unsupported[] = $action;
        return 'unsupported:'.$action;
    }
}
