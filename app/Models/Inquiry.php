<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\AppointmentBookingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Inquiry extends Model
{
    use BelongsToTenant;

    private ?string $pendingMeetingMode = null;

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
        'meeting_mode',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function setMeetingModeAttribute(mixed $value): void
    {
        $this->pendingMeetingMode = filled($value) ? (string) $value : null;
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

            if ($inquiry->pendingMeetingMode) {
                $meta = (array) $inquiry->meta;
                $meeting = (array) data_get($meta, 'meeting', []);
                $meeting['mode'] = $inquiry->pendingMeetingMode;
                $meta['meeting'] = $meeting;
                $inquiry->meta = $meta;
            }
        });

        static::created(function (self $inquiry): void {
            $meeting = (array) data_get($inquiry->meta, 'meeting', []);
            $date = (string) ($meeting['date'] ?? '');
            $time = (string) ($meeting['time'] ?? '');
            $mode = (string) ($meeting['mode'] ?? '');
            if ($date === '' && $time === '' && $mode === '') {
                return;
            }

            $service = app(AppointmentBookingService::class);
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [
                    'appointment:'.(int) $inquiry->company_id.':'.$date,
                ]);
            }
            $service->validateSelection($date, $time, $mode, (int) $inquiry->company_id);

            AppointmentBooking::withoutGlobalScopes()->create([
                'company_id' => $inquiry->company_id,
                'inquiry_id' => $inquiry->id,
                'meeting_date' => $date,
                'meeting_time' => $time,
                'meeting_mode' => $mode,
                'status' => 'booked',
            ]);
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

    public function appointmentBooking(): HasOne
    {
        return $this->hasOne(AppointmentBooking::class);
    }
}
