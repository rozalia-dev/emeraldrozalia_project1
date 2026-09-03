<?php

namespace App\Support;

final class Money
{
    /**
     * Project 1 stores customer-facing amounts as decimal(12,2) EUR values.
     * Round half-up at every persisted boundary so totals do not drift.
     */
    public static function round(int|float|string $amount): float
    {
        return round((float) $amount, 2, PHP_ROUND_HALF_UP);
    }

    public static function multiply(int|float|string $amount, int $quantity): float
    {
        return self::round((float) $amount * $quantity);
    }
}
