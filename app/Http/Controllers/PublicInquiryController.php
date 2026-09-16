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
        $type = (string) ($data['type'] ?? 'contact');
        $requiresConsent = in_array($type, ['contact', 'franchise'], true);
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
        $business = $this->businessContext($type, $data, $country);
        foreach ([
            'meeting_date', 'meeting_time', 'consent', 'country',
            'product_interest', 'estimated_quantity', 'branding_requirement', 'required_by',
            'preferred_location', 'investment_range', 'business_experience', 'opening_timeline',
        ] as $field) {
            unset($data[$field]);
        }

        $data['email'] = strtolower(trim((string) $data['email']));
        $data['meta'] = [
            'source' => 'public_'.$type.'_form',
            'meeting' => $meeting ?: null,
            'country' => $country,
            'business' => $business ?: null,
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
            'business' => $business,
        ], JSON_UNESCAPED_SLASHES));

        if ($idempotencyKey !== '') {
            $existing = Inquiry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                    abort(409, 'The Idempotency-Key was already used for a different enquiry.');
                }

                return back()->with('success', $this->successMessage((string) $existing->type, true));
            }
        }

        try {
            DB::transaction(function () use (
                $data,
                $type,
                $country,
                $business,
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
                if ($type === 'franchise') {
                    $application = FranchiseApplication::create([
                        'applicant_name' => $data['name'],
                        'email' => $data['email'],
                        'phone' => $data['phone'] ?? null,
                        'territory' => $country ?: 'Ireland',
                        'preferred_location' => $business['preferred_location'] ?? $data['company'] ?? null,
                        'investment_range' => $business['investment_range'] ?? null,
                        'business_experience' => $business['business_experience'] ?? $data['message'] ?? null,
                        'status' => 'new',
                        'data' => [
                            'source' => 'public_franchise_form',
                            'country' => $country,
                            'opening_timeline' => $business['opening_timeline'] ?? null,
                            'motivation' => $data['message'] ?? null,
                            'correlation_id' => $correlationId,
                        ],
                        'correlation_id' => $correlationId,
                        'inquiry_id' => $inquiry->id,
                    ]);
                }

                $conversation = $type === 'contact'
                    ? $this->activeContactConversation($inquiry->company_id, $data['email'])
                    : null;
                $conversationCreated = $conversation === null;
                $before = $conversation?->toArray();

                $metadata = [
                    'type' => $type,
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null,
                    'company' => $data['company'] ?? null,
                    'country' => $country,
                    'meeting' => $meeting ?: null,
                    'business' => $business ?: null,
                    'inquiry_uuid' => (string) $inquiry->public_uuid,
                    'franchise_application_uuid' => $application?->uuid,
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
                        'subject' => $data['subject'] ?? str($type)->headline(),
                        'priority' => $type === 'franchise' ? 'high' : 'normal',
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
                if ($business) {
                    $body .= "\n\nBusiness intake:\n".$this->formatBusinessContext($business);
                }
                if ($meeting) {
                    $body .= "\n\nMeeting requested: {$meeting['date']} at {$meeting['time']} (Europe/Dublin).";
                }
                $message = $conversation->messages()->create([
                    'direction' => 'inbound',
                    'body' => $body,
                    'delivery_status' => 'stored',
                    'idempotency_key' => $messageIdempotencyKey,
                    'payload' => [
                        'source' => 'public_'.$type.'_form',
                        'correlation_id' => $correlationId,
                        'consent_captured' => (bool) $consentCapturedAt,
                        'business' => $business ?: null,
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
                        'domain' => $type === 'franchise' ? 'franchise_application' : ($this->isQuoteRequest($type) ? 'sales_quote' : 'communication'),
                    ],
                );

                // Corporate and Bulk public forms are quote/order-intake workflows.
                // They are linked to Communication Centre for correspondence, but
                // sales_quotes is the pre-order source of truth until conversion.
                // A franchise application is NOT a franchise product order.
                $quote = null;
                if ($this->isQuoteRequest($type)) {
                    $quote = app(SalesQuoteService::class)->createFromInquiry($inquiry, $conversation);
                }

                $inquiryMeta = is_array($inquiry->meta) ? $inquiry->meta : [];
                if ($quote) {
                    $inquiryMeta['quote_uuid'] = (string) $quote->uuid;
                    $inquiryMeta['order_master_type'] = (string) $quote->order_type;
                    $inquiryMeta['business_stage'] = 'quote_submitted';
                }
                if ($application) {
                    $inquiryMeta['franchise_application_uuid'] = (string) $application->uuid;
                    $inquiryMeta['business_stage'] = 'franchise_application_new';
                }
                $inquiry->update(['meta' => $inquiryMeta]);

                $conversationMeta = is_array($conversation->metadata) ? $conversation->metadata : [];
                if ($quote) {
                    $conversationMeta['quote_uuid'] = (string) $quote->uuid;
                    $conversationMeta['quote_status'] = (string) $quote->status;
                    $conversationMeta['order_master_type'] = (string) $quote->order_type;
                }
                if ($application) {
                    $conversationMeta['franchise_application_uuid'] = (string) $application->uuid;
                    $conversationMeta['franchise_status'] = (string) $application->status;
                }
                $conversation->update(['metadata' => $conversationMeta]);
            });
        } catch (QueryException $exception) {
            if ($idempotencyKey === '') {
                throw $exception;
            }

            $existing = Inquiry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing && hash_equals((string) $existing->request_hash, $requestHash)) {
                return back()->with('success', $this->successMessage((string) $existing->type, true));
            }

            throw $exception;
        }

        return back()->with('success', $this->successMessage($type));
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

    private function isQuoteRequest(string $type): bool
    {
        return in_array($type, ['corporate-orders', 'bulk-orders'], true);
    }

    private function businessContext(string $type, array $data, ?string $country): array
    {
        if ($this->isQuoteRequest($type)) {
            return array_filter([
                'order_master_type' => $type === 'corporate-orders' ? 'corporate' : 'bulk',
                'product_interest' => $data['product_interest'] ?? null,
                'estimated_quantity' => isset($data['estimated_quantity']) ? (int) $data['estimated_quantity'] : null,
                'branding_requirement' => $data['branding_requirement'] ?? null,
                'required_by' => $data['required_by'] ?? null,
                'company' => $data['company'] ?? null,
                'country' => $country,
            ], static fn ($value): bool => $value !== null && $value !== '');
        }

        if ($type === 'franchise') {
            return array_filter([
                'preferred_location' => $data['preferred_location'] ?? $data['company'] ?? null,
                'investment_range' => $data['investment_range'] ?? null,
                'business_experience' => $data['business_experience'] ?? null,
                'opening_timeline' => $data['opening_timeline'] ?? null,
                'country' => $country,
            ], static fn ($value): bool => $value !== null && $value !== '');
        }

        return [];
    }

    private function formatBusinessContext(array $business): string
    {
        return collect($business)
            ->map(fn ($value, $key): string => str((string) $key)->replace('_', ' ')->headline().': '.(is_scalar($value) ? (string) $value : json_encode($value)))
            ->implode("\n");
    }

    private function successMessage(string $type, bool $duplicate = false): string
    {
        $prefix = $duplicate ? 'This submission was already received. ' : 'Thank you. ';

        return match ($type) {
            'corporate-orders' => $prefix.'Your Corporate quote request is in Sales Quotes & Conversions and linked to the Communication Centre. Once approved and converted it becomes a Corporate Order.',
            'bulk-orders' => $prefix.'Your Bulk quote request is in Sales Quotes & Conversions and linked to the Communication Centre. Once approved and converted it becomes a Bulk Order.',
            'franchise' => $prefix.'Your Franchise application is in Franchise Management → Applications & Leads and linked to the Communication Centre.',
            'careers' => $prefix.'Your application has been received and is linked to the Communication Centre.',
            default => $prefix.'Your enquiry is now in our Communication Centre.',
        };
    }
}
