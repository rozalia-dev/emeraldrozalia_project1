<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AppointmentBooking extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'inquiry_id', 'uuid', 'meeting_date', 'meeting_time',
        'meeting_mode', 'status', 'meeting_link', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $booking) => $booking->uuid ??= (string) Str::uuid());
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }
}
