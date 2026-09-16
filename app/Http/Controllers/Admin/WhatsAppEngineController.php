<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Throwable;

class WhatsAppEngineController extends Controller
{
    public function index(): View
    {
        return view('admin.communication-center.whatsapp-engine', [
            'configured' => $this->isConfigured(),
            'engineUrl' => rtrim((string) config('communication.whatsapp_engine_url'), '/'),
        ]);
    }

    public function status(): JsonResponse
    {
        return $this->proxy('/status');
    }

    public function qr(): JsonResponse
    {
        return $this->proxy('/qr-data');
    }

    private function proxy(string $path): JsonResponse
    {
        if (! $this->isConfigured()) {
            return response()->json([
                'ok' => false,
                'ready' => false,
                'state' => 'not_configured',
                'error' => 'The WhatsApp Web engine is not configured on this server.',
            ], 503);
        }

        try {
            $response = $this->client()->get($path);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json([
                'ok' => false,
                'ready' => false,
                'state' => 'engine_unavailable',
                'error' => 'The WhatsApp Web engine is not reachable.',
            ], 503);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            $payload = [
                'ok' => false,
                'ready' => false,
                'state' => 'invalid_engine_response',
                'error' => 'The WhatsApp Web engine returned an invalid response.',
            ];
        }

        return response()->json($payload, $response->status());
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('communication.whatsapp_engine_url'), '/'))
            ->acceptJson()
            ->withToken((string) config('communication.tokens.whatsapp'))
            ->connectTimeout(3)
            ->timeout(10);
    }

    private function isConfigured(): bool
    {
        return filled(config('communication.whatsapp_engine_url'))
            && filled(config('communication.tokens.whatsapp'))
            && filled(config('communication.webhook_secrets.whatsapp'));
    }
}
