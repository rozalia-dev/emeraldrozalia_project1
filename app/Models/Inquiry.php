<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\AppointmentBookingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Inquiry extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'customer_id',
        'type',
        'name',
        'email',
        'phone',
        'company',
        'subject',
        'message',
        'meta',
        'status',
        'correlation_id',
        'idempotency_key',
        'request_hash',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $inquiry): void {
            if (! $inquiry->customer_id) {
                $user = auth()->user();
                if ($user && $user->hasVerifiedEmail()
                    && mb_strtolower(trim((string) $user->email)) === mb_strtolower(trim((string) $inquiry->email))) {
                    $inquiry->customer_id = $user->id;
                }
            }

            $meta = (array) $inquiry->meta;
            if (filled(data_get($meta, 'meeting.date')) && filled(data_get($meta, 'meeting.time'))) {
                $booking = app(AppointmentBookingService::class);
                if (! $inquiry->company_id) {
                    $inquiry->company_id = $booking->resolveCompanyId();
                }
                $meetingType = trim((string) request()->input('meeting_type', ''));
                if ($meetingType !== '') {
                    data_set($meta, 'meeting.type', $meetingType);
                    $inquiry->meta = $meta;
                }
            }
        });

        static::created(function (self $inquiry): void {
            app(AppointmentBookingService::class)->reserveInquiry($inquiry);
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function franchiseApplication(): HasOne
    {
        return $this->hasOne(FranchiseApplication::class);
    }

    public function salesQuote(): HasOne
    {
        return $this->hasOne(SalesQuote::class);
    }

    public function appointmentReservation(): HasOne
    {
        return $this->hasOne(AppointmentReservation::class);
    }
}
