<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\{ExternalServiceGate, IntegrationConnectionService};

class IntegrationStatusController extends Controller
{
    public function __invoke(IntegrationConnectionService $connections)
    {
        return response()->json([
            'data' => $connections->all(),
            'activation_sequence' => [
                'Deploy core website',
                'Run migrations and tests',
                'Verify SSL, queue, cron and backup restore',
                'Put core website live',
                'Enter one provider credential set',
                'Run the configuration and runtime gate probe',
                'Verify callback and webhook signature',
                'Enable that provider only',
            ],
            'probe_semantics' => 'Configuration and activation readiness only; no provider is labelled healthy without an external handshake or signed callback evidence.',
        ]);
    }
}
