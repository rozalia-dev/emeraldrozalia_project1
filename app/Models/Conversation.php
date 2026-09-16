<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'follow_up_at' => 'datetime',
            'consent_captured_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $row): void {
            $row->uuid ??= Str::uuid()->toString();

            if (! $row->customer_id || ! $row->inquiry_id) {
                return;
            }

            $customer = User::query()->find($row->customer_id);
            $inquiry = Inquiry::withoutGlobalScopes()->find($row->inquiry_id);
            $customerEmail = mb_strtolower(trim((string) $customer?->email));
            $inquiryEmail = mb_strtolower(trim((string) $inquiry?->email));
            $contactEmail = mb_strtolower(trim((string) $row->contact));

            if (! $customer
                || ! $customer->hasVerifiedEmail()
                || $customerEmail === ''
                || $customerEmail !== $inquiryEmail
                || $customerEmail !== $contactEmail) {
                $row->customer_id = null;
            }
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function franchiseApplication(): BelongsTo
    {
        return $this->belongsTo(FranchiseApplication::class);
    }

    public function salesQuote()
    {
        return $this->hasOne(SalesQuote::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeForCurrentCompany(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        // The Inbox owns its From / To controls. Apply the selected range at
        // the query scope used by both the Inbox list and its export endpoint,
        // including installations that do not have a selected company session.
        if (request()->is('admin/resource/inbox') || request()->is('admin/communication-center/inbox/export')) {
            if ($from = $this->validInboxDate(request()->query('date_from'))) {
                $query->where($table.'.created_at', '>=', $from.' 00:00:00');
            }
            if ($to = $this->validInboxDate(request()->query('date_to'))) {
                $query->where($table.'.created_at', '<=', $to.' 23:59:59.999999');
            }
        }

        $companyId = session('company_id');
        if (! $companyId) {
            return $query;
        }

        $query->withoutGlobalScope('tenant')->where(function (Builder $visible) use ($table, $companyId): void {
            $visible->where($table.'.company_id', (int) $companyId);

            // Global administrators may still resolve legacy conversations whose
            // tenant was not recoverable during the ownership backfill. New
            // records are always assigned by BelongsToTenant.
            if (auth()->user()?->is_admin) {
                $visible->orWhereNull($table.'.company_id');
            }
        });

        return $query;
    }

    private function validInboxDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();
        $table = $this->getTable();

        // Implicit binding can run before the appended web middleware has
        // established the selected company. Build from the model query (no
        // global scopes), then apply the visibility rule explicitly.
        $query = $this->newModelQuery()
            ->whereNull($this->getQualifiedDeletedAtColumn())
            ->where($table.'.'.$field, $value);

        $companyId = session('company_id');
        if ($companyId) {
            $query->where(function (Builder $visible) use ($table, $companyId): void {
                $visible->where($table.'.company_id', (int) $companyId);
                if (auth()->user()?->is_admin) {
                    $visible->orWhereNull($table.'.company_id');
                }
            });
        }

        return $query->first();
    }

    public function resolveSoftDeletableRouteBinding($value, $field = null)
    {
        return $this->resolveRouteBinding($value, $field);
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return $this->scopeForCurrentCompany(
            parent::resolveRouteBindingQuery($query, $value, $field),
        );
    }
}
