<?php

namespace App\Services;

use App\Models\{BackupRun, MaintenanceRun};
use Illuminate\Support\Facades\{Cache, DB, Storage};
use Illuminate\Support\Str;
use Throwable;

final class SystemMaintenanceService
{
    public const CHECKS = [
        'database',
        'cache',
        'storage',
        'queue',
        'scheduler',
        'integrations',
        'verified_backup',
    ];

    public function run(?int $companyId = null): MaintenanceRun
    {
        $companyId ??= session('company_id') ? (int) session('company_id') : null;

        $run = MaintenanceRun::create([
            'company_id' => $companyId,
            'triggered_by' => auth()->id(),
            'status' => 'running',
            'started_at' => now(),
        ]);

        $checks = [];
        foreach (self::CHECKS as $check) {
            $checks[$check] = match ($check) {
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
                'queue' => $this->checkQueue(),
                'scheduler' => $this->checkScheduler(),
                'integrations' => $this->checkIntegrations(),
                'verified_backup' => $this->checkVerifiedBackup(),
            };
        }

        $failed = collect($checks)->where('status', 'failed')->isNotEmpty();
        $attention = collect($checks)->where('status', 'attention')->isNotEmpty();
        $status = $failed ? 'failed' : ($attention ? 'attention' : 'passed');

        $run->forceFill([
            'status' => $status,
            'checks' => $checks,
            'completed_at' => now(),
        ])->save();

        AuditTrail::record('settings.maintenance.completed', $run->fresh(), null, [
            'status' => $status,
            'checks' => collect($checks)->mapWithKeys(
                fn (array $result, string $name): array => [$name => [
                    'status' => $result['status'],
                    'message' => $result['message'],
                ]],
            )->all(),
        ]);

        return $run->fresh();
    }

    private function checkDatabase(): array
    {
        return $this->evaluate(function (): array {
            DB::select('select 1');

            return ['status' => 'passed', 'message' => 'Database connectivity is responding.'];
        });
    }

    private function checkCache(): array
    {
        return $this->evaluate(function (): array {
            $store = Cache::store();
            $key = 'maintenance-probe-'.Str::uuid();

            try {
                $stored = $store->put($key, 'ok', 10);

                if (! $stored || $store->get($key) !== 'ok') {
                    throw new \RuntimeException('Cache write/read verification did not round-trip.');
                }

                return ['status' => 'passed', 'message' => 'Cache write/read verification passed.'];
            } finally {
                $store->forget($key);
            }
        });
    }

    private function checkStorage(): array
    {
        return $this->evaluate(function (): array {
            $disk = Storage::disk('local');
            $path = 'maintenance-probe-'.Str::uuid().'.txt';

            try {
                if (! $disk->put($path, 'maintenance probe')) {
                    throw new \RuntimeException('Private storage write was rejected.');
                }

                if (! $disk->exists($path)) {
                    throw new \RuntimeException('Private storage read-after-write was rejected.');
                }

                return ['status' => 'passed', 'message' => 'Private storage write/read verification passed.'];
            } finally {
                $disk->delete($path);
            }
        });
    }

    private function checkQueue(): array
    {
        return $this->evaluate(function (): array {
            $driver = (string) config('queue.default');
            $connection = config('queue.connections.'.$driver);

            if ($driver === '' || ! is_array($connection)) {
                throw new \RuntimeException('The configured queue connection is unavailable.');
            }

            return [
                'status' => 'passed',
                'message' => 'Queue connection '.$driver.' is configured; worker heartbeat is checked separately.',
            ];
        });
    }

    private function checkScheduler(): array
    {
        return $this->evaluate(function (): array {
            if (! is_file(base_path('routes/console.php'))) {
                throw new \RuntimeException('The scheduler command definition is missing.');
            }

            return [
                'status' => 'attention',
                'message' => 'Scheduler definition is present; the last scheduler heartbeat must be verified by the running worker.',
            ];
        });
    }

    private function checkIntegrations(): array
    {
        return $this->evaluate(function (): array {
            $states = app(IntegrationConnectionService::class)->all();
            $attention = collect($states)->filter(
                fn (array $state): bool => in_array($state['health'], ['not_configured', 'blocked', 'configured'], true),
            )->count();

            return [
                'status' => $attention > 0 ? 'attention' : 'passed',
                'message' => $attention > 0
                    ? $attention.' integration(s) require configuration, activation, or external verification.'
                    : 'All configured integrations passed the application readiness gate.',
            ];
        });
    }

    private function checkVerifiedBackup(): array
    {
        return $this->evaluate(function (): array {
            $backup = BackupRun::query()
                ->where('status', 'completed')
                ->whereNotNull('verified_at')
                ->latest('verified_at')
                ->first();

            return $backup
                ? ['status' => 'passed', 'message' => 'A verified settings backup is available.']
                : ['status' => 'attention', 'message' => 'No verified settings backup is available for this tenant.'];
        });
    }

    private function evaluate(callable $probe): array
    {
        try {
            $result = $probe();
            $status = in_array($result['status'] ?? null, ['passed', 'attention', 'failed'], true)
                ? $result['status']
                : 'failed';

            return [
                'status' => $status,
                'message' => (string) ($result['message'] ?? 'Maintenance check completed.'),
                'checked_at' => now()->toIso8601String(),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => Str::limit($exception->getMessage() ?: 'Maintenance check failed.', 500, ''),
                'checked_at' => now()->toIso8601String(),
            ];
        }
    }
}
