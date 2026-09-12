<?php

namespace App\Http\Controllers;

use App\Services\CommunicationWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CommunicationWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, CommunicationWebhookService $service): JsonResponse
    {
        $correlationId = $request->attributes->get('correlation_id');
        if (! is_string($correlationId) || ! Str::isUuid($correlationId)) {
            $correlationId = (string) Str::uuid();
            $request->attributes->set('correlation_id', $correlationId);
        }

        $result = $service->handle(
            $provider,
            $request->getContent(),
            (string) $request->header('X-Communication-Signature', ''),
        );

        return response()
            ->json($result)
            ->header('X-Correlation-ID', $correlationId);
    }
}
