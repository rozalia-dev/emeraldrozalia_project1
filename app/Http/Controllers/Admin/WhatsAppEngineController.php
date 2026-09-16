<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\AuditTrail;
use App\Services\CommunicationCenter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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

    public function send(Request $request, CommunicationCenter $communication): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:25'],
            'message' => ['required', 'string', 'max:4096'],
        ]);

        $phone = preg_replace('/\D+/', '', (string) $data['phone']);
        if (! is_string($phone) || ! preg_match('/^\d{7,15}$/', $phone)) {
            return response()->json([
                'message' => 'Use the full international WhatsApp number, for example 353871234567.',
                'errors' => ['phone' => ['A valid international phone number is required.']],
            ], 422);
        }

        if (! $this->isConfigured()) {
            return response()->json(['message' => 'The WhatsApp Web engine is not configured.'], 503);
        }

        try {
            $engine = $this->client()->get('/status');
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'The WhatsApp Web engine is not reachable.'], 503);
        }

        if (! $engine->successful() || ! (bool) $engine->json('ready')) {
            return response()->json([
                'message' => 'WhatsApp is not connected yet. Open this setup page and scan the QR code first.',
            ], 409);
        }

        $companyId = session('company_id') ? (int) session('company_id') : null;
        $conversation = DB::transaction(function () use ($phone, $companyId): Conversation {
            $query = Conversation::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('channel', 'whatsapp')
                ->where('contact', $phone);

            $companyId
                ? $query->where('company_id', $companyId)
                : $query->whereNull('company_id');

            $conversation = $query->lockForUpdate()->first();
            if ($conversation) {
                if (in_array($conversation->status, ['closed', 'resolved'], true)) {
                    $conversation->update(['status' => 'open']);
                }
                return $conversation->fresh();
            }

            $conversation = Conversation::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'channel' => 'whatsapp',
                'contact' => $phone,
                'subject' => 'WhatsApp · '.$phone,
                'priority' => 'normal',
                'status' => 'open',
                'correlation_id' => (string) Str::uuid(),
                'metadata' => [
                    'name' => $phone,
                    'source' => 'communication_center_whatsapp_compose',
                ],
            ]);

            AuditTrail::record('communication.whatsapp.conversation_created', $conversation, null, [
                'conversation_uuid' => $conversation->uuid,
                'channel' => 'whatsapp',
                'contact' => $phone,
            ]);

            return $conversation;
        });

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = 'wa-compose:'.Str::uuid()->toString();
        }

        $message = $communication->sendReply(
            $conversation,
            (string) $data['message'],
            $idempotencyKey,
        );

        return response()->json([
            'queued' => true,
            'conversation_uuid' => $conversation->uuid,
            'message_uuid' => $message->uuid,
            'delivery_status' => $message->delivery_status,
            'inbox_url' => url('/admin/resource/whatsapp?conversation='.$conversation->id),
        ], 202);
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
