<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['active' => 'boolean', 'decimals' => 'integer', 'sort_order' => 'integer'];

    public function format(int|float|string $amount): string
    {
        $formatted = number_format(
            (float) $amount,
            (int) ($this->decimals ?? 2),
            (string) ($this->decimal_separator ?: '.'),
            (string) ($this->thousands_separator ?: ','),
        );
        $symbol = (string) ($this->symbol ?: $this->code);

        return ($this->symbol_position ?? 'before') === 'after'
            ? $formatted.' '.$symbol
            : $symbol.$formatted;
    }
}
