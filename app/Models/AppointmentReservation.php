<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class AppointmentReservation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'uuid',
        'company_id',
        'inquiry_id',
        'appointment_date',
        'appointment_time',
        'meeting_type',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $reservation): string => $reservation->uuid ??= Str::uuid()->toString());
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
