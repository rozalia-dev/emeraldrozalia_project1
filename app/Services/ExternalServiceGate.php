<?php

namespace App\Services;

use RuntimeException;

final class ExternalServiceGate
{
    public function status(string $service): array
    {
        $config = config('external.'.$service);
        abort_unless(is_array($config), 404);

        $required = collect($config)
            ->except('enabled')
            ->keys()
            ->values();
        $missing = $required
            ->filter(fn (string $key): bool => blank($config[$key] ?? null))
            ->values()
            ->all();
        $configured = $required->isNotEmpty() && $missing === [];

        return [
            'service' => $service,
            'provider' => $config['provider'] ?? null,
            'enabled' => (bool) ($config['enabled'] ?? false),
            'configured' => $configured,
            'live' => (bool) ($config['enabled'] ?? false) && $configured,
            'missing' => $missing,
            'source' => 'environment',
        ];
    }

    public function requireLive(string $service): void
    {
        $status = $this->status($service);

        if (! $status['live']) {
            throw new RuntimeException("$service is disabled until after the core site is deployed, verified and live.");
        }
    }

    public function all(): array
    {
        return collect(array_keys(config('external')))
            ->mapWithKeys(fn (string $service): array => [$service => $this->status($service)])
            ->all();
    }
}
