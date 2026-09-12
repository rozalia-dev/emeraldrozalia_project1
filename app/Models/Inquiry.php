<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Inquiry extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
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

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function franchiseApplication(): HasOne
    {
        return $this->hasOne(FranchiseApplication::class);
    }
}
