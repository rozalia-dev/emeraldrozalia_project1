<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRun;
use Illuminate\Http\JsonResponse;

class SystemMaintenanceController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $runs = MaintenanceRun::query()
            ->with('triggeredBy:id,name')
            ->latest('started_at')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $runs->map(fn (MaintenanceRun $run): array => [
                'uuid' => $run->uuid,
                'status' => $run->status,
                'checks' => $run->checks ?? [],
                'error' => $run->error,
                'started_at' => $run->started_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
                'triggered_by' => $run->triggeredBy?->name,
            ])->values(),
            'semantics' => 'Application-level checks only. Database/file restore, worker heartbeats, and external provider handshakes remain separate release gates.',
        ]);
    }
}
