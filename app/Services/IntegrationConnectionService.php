<?php

namespace App\Services;

use App\Models\IntegrationConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class IntegrationConnectionService
{
    public const SERVICES = [
        'email', 'chat', 'whatsapp', 'payment', 'shipping', 'social', 'try_on', 'product_360',
    ];

    public function all(): array
    {
        $connections = $this->visibleConnections()->get()->keyBy('service');

        return collect(self::SERVICES)
            ->mapWithKeys(function (string $service) use ($connections): array {
                $connection = $connections->get($service);
                $gate = app(ExternalServiceGate::class)->status($service);

                return [$service => $this->state($service, $connection, $gate)];
            })
            ->all();
    }

    /**
     * Probe configuration and runtime activation gates. This intentionally
     * does not call an external provider or label a config check as a live
     * delivery handshake.
     */
    public function probe(IntegrationConnection $connection): array
    {
        $this->assertTenant($connection);
        $service = (string) $connection->service;
        abort_unless(in_array($service, self::SERVICES, true), 404);

        $gate = app(ExternalServiceGate::class)->status($service);
        $state = $this->state($service, $connection, $gate);
        if ($state['health'] === 'configured' && $state['runtime_live']) {
            $state['health'] = 'ready';
        }
        $message = match ($state['health']) {
            'not_configured' => 'Required provider configuration is missing: '.implode(', ', $state['missing']).'.',
            'blocked' => 'Configuration is present, but the runtime activation gate is disabled. Keep this integration disabled until the core site and callback verification are complete.',
            'ready' => 'Configuration gate passed. A provider-specific handshake or signed callback replay is still required before enabling production traffic.',
            default => 'Provider configuration is saved but has not passed the runtime activation gate.',
        };

        $connection->forceFill([
            'health' => $state['health'],
            'health_message' => $message,
            'tested_at' => now(),
            'last_checked_at' => now(),
        ])->save();

        AuditTrail::record('settings.integration.probed', $connection, null, [
            'service' => $service,
            'health' => $state['health'],
            'configured' => $state['configured'],
            'runtime_live' => $state['runtime_live'],
            'missing' => $state['missing'],
        ]);

        return [...$state, 'message' => $message];
    }

    private function state(string $service, ?IntegrationConnection $connection, array $gate): array
    {
        $credentials = is_array($connection?->encrypted_credentials) ? $connection->encrypted_credentials : [];
        $storedConfigured = filled($connection?->provider)
            && collect($credentials)->filter(fn ($value): bool => filled($value))->isNotEmpty();
        $configured = (bool) ($gate['configured'] ?? false) || $storedConfigured;
        $runtimeLive = (bool) ($gate['live'] ?? false);
        $persistedHealth = (string) ($connection?->health ?? '');

        $health = match (true) {
            ! $configured => 'not_configured',
            ! $runtimeLive => 'blocked',
            $persistedHealth === 'ready' && $connection?->last_checked_at !== null => 'ready',
            default => 'configured',
        };

        return [
            'service' => $service,
            'provider' => $connection?->provider ?: ($gate['provider'] ?? null),
            'enabled' => (bool) ($connection?->enabled ?? false),
            'health' => $health,
            'configured' => $configured,
            'runtime_live' => $runtimeLive,
            'missing' => $gate['missing'] ?? [],
            'health_message' => $connection?->health_message,
            'tested_at' => $connection?->tested_at?->toIso8601String(),
            'last_checked_at' => $connection?->last_checked_at?->toIso8601String(),
        ];
    }

    private function visibleConnections(): Builder
    {
        $query = IntegrationConnection::query();
        $companyId = session('company_id');

        if ($companyId) {
            return $query;
        }

        return $query->whereNull('company_id');
    }

    private function assertTenant(IntegrationConnection $connection): void
    {
        $companyId = session('company_id');

        if ($companyId && $connection->company_id && (int) $connection->company_id !== (int) $companyId) {
            abort(404);
        }
    }
}
