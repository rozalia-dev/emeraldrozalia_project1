<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicInquiryRequest;
use App\Models\Conversation;
use App\Models\FranchiseApplication;
use App\Models\Inquiry;
use App\Services\AuditTrail;
use App\Services\SalesQuoteService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicInquiryController extends Controller
{
    public function __invoke(PublicInquiryRequest $request)
    {
        $data = $request->validated();
        $requiresConsent = in_array((string) ($data['type'] ?? ''), ['contact', 'franchise'], true);
        $meeting = array_filter([
            'date' => $data['meeting_date'] ?? null,
            'time' => $data['meeting_time'] ?? null,
        ], fn ($value) => filled($value));

        if ($meeting) {
            $slot = CarbonImmutable::createFromFormat(
                '!Y-m-d H:i',
                $meeting['date'].' '.$meeting['time'],
                config('app.timezone'),
            );
            if ($slot->isWeekend()) {
                throw ValidationException::withMessages([
                    'meeting_date' => 'Meetings are available Monday to Friday only.',
                ]);
            }
            if ($slot->lessThanOrEqualTo(now(config('app.timezone')))) {
                throw ValidationException::withMessages([
                    'meeting_time' => 'Please choose a future meeting time.',
                ]);
            }
        }

        $country = $data['country'] ?? null;
        unset($data['meeting_date'], $data['meeting_time'], $data['consent'], $data['country']);

        $data['email'] = strtolower(trim((string) $data['email']));
        $data['meta'] = [
            'source' => 'public_'.$data['type'].'_form',
            'meeting' => $meeting ?: null,
            'country' => $country,
        ];

        $idempotencyKey = (string) ($data['idempotency_key'] ?? '');
        unset($data['idempotency_key']);

        $correlationId = (string) ($request->attributes->get('correlation_id') ?: Str::uuid());
        $customerId = $request->user()?->id;
        $messageIdempotencyKey = $idempotencyKey !== ''
            ? hash('sha256', 'public-inbound:'.$idempotencyKey)
            : null;
        $consentCapturedAt = $requiresConsent ? now() : null;
        $requestHash = hash('sha256', (string) json_encode([
            'payload' => $data,
            'meeting' => $meeting,
        ], JSON_UNESCAPED_SLASHES));

        if ($idempotencyKey !== '') {
            $existing = Inquiry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                    abort(409, 'The Idempotency-Key was already used for a different enquiry.');
                }

                return back()->with('success', 'This enquiry was already received and is in our Communication Centre.');
            }
        }

        try {
            DB::transaction(function () use (
                $data,
                $meeting,
                $correlationId,
                $idempotencyKey,
                $requestHash,
                $customerId,
                $messageIdempotencyKey,
                $consentCapturedAt,
            ): void {
                $inquiry = Inquiry::create(array_merge($data, [
                    'correlation_id' => $correlationId,
                    'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                    'request_hash' => $requestHash,
                ]));

                $application = null;
                if ($data['type'] === 'franchise') {
                    $application = FranchiseApplication::create([
                        'applicant_name' => $data['name'],
                        'email' => $data['email'],
                        'phone' => $data['phone'] ?? null,
                        'territory' => 'Ireland',
                        'preferred_location' => $data['company'] ?? null,
                        'business_experience' => $data['message'] ?? null,
                        'status' => 'new',
                        'data' => ['source' => 'public_franchise_form'],
                        'correlation_id' => $correlationId,
                        'inquiry_id' => $inquiry->id,
                    ]);
                }

                $conversation = $data['type'] === 'contact'
                    ? $this->activeContactConversation($inquiry->company_id, $data['email'])
                    : null;
                $conversationCreated = $conversation === null;
                $before = $conversation?->toArray();

                $metadata = [
                    'type' => $data['type'],
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null,
                    'company' => $data['company'] ?? null,
                    'country' => $data['meta']['country'] ?? null,
                    'meeting' => $meeting ?: null,
                ];

                if ($conversation) {
                    $updates = [
                        'contact' => $data['email'],
                        'subject' => $data['subject'] ?? $conversation->subject,
                        'status' => 'new',
                        'metadata' => array_merge((array) $conversation->metadata, $metadata),
                    ];
                    if (! $conversation->customer_id && $customerId) {
                        $updates['customer_id'] = $customerId;
                    }
                    $conversation->update($updates);
                    $conversation->refresh();
                } else {
                    $conversation = Conversation::create([
                        'company_id' => $inquiry->company_id,
                        'customer_id' => $customerId,
                        'channel' => 'web',
                        'contact' => $data['email'],
                        'subject' => $data['subject'] ?? str($data['type'])->headline(),
                        'priority' => $data['type'] === 'franchise' ? 'high' : 'normal',
                        'status' => 'new',
                        'correlation_id' => $correlationId,
                        'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                        'request_hash' => $requestHash,
                        'consent_captured_at' => $consentCapturedAt,
                        'consent_version' => $consentCapturedAt ? 'public-enquiry-v1' : null,
                        'inquiry_id' => $inquiry->id,
                        'franchise_application_id' => $application?->id,
                        'metadata' => $metadata,
                    ]);
                }

                $body = $data['message'] ?? 'Public form submission';
                if ($meeting) {
                    $body .= "\n\nMeeting requested: {$meeting['date']} at {$meeting['time']} (Europe/Dublin).";
                }
                $message = $conversation->messages()->create([
                    'direction' => 'inbound',
                    'body' => $body,
                    'delivery_status' => 'stored',
                    'idempotency_key' => $messageIdempotencyKey,
                    'payload' => [
                        'source' => 'public_'.$data['type'].'_form',
                        'correlation_id' => $correlationId,
                        'consent_captured' => (bool) $consentCapturedAt,
                    ],
                    'sent_at' => now(),
                ]);

                AuditTrail::record(
                    $conversationCreated
                        ? 'communication.public_submission.created'
                        : 'communication.public_submission.appended',
                    $conversation,
                    $before,
                    [
                        'uuid' => (string) $conversation->uuid,
                        'channel' => $conversation->channel,
                        'correlation_id' => $correlationId,
                        'idempotency_key' => $conversation->idempotency_key,
                        'consent_captured' => (bool) $consentCapturedAt,
                        'message_uuid' => (string) $message->uuid,
                    ],
                );

                $quoteService = app(SalesQuoteService::class);
                if ($quoteService->orderTypeForInquiryType((string) $data['type']) !== null) {
                    $quoteService->createFromInquiry($inquiry, $conversation, $application);
                }
            });
        } catch (QueryException $exception) {
            if ($idempotencyKey === '') {
                throw $exception;
            }

            $existing = Inquiry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing && hash_equals((string) $existing->request_hash, $requestHash)) {
                return back()->with('success', 'This enquiry was already received and is in our Communication Centre.');
            }

            throw $exception;
        }

        return back()->with('success', 'Thank you. Your enquiry is now in our Communication Centre.');
    }

    private function activeContactConversation(?int $companyId, string $email): ?Conversation
    {
        $query = Conversation::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('channel', 'web')
            ->whereIn('status', ['new', 'open', 'pending'])
            ->whereRaw('LOWER(contact) = ?', [strtolower(trim($email))])
            ->where('metadata->type', 'contact');

        $companyId
            ? $query->where('company_id', $companyId)
            : $query->whereNull('company_id');

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }
}
