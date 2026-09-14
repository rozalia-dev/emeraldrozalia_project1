<?php

namespace App\Support;

use InvalidArgumentException;
use OverflowException;

final class Money
{
    /**
     * Project 1 stores customer-facing amounts as decimal(12,2) values.
     *
     * All arithmetic in the checkout boundary is performed in minor units
     * (cents). Values cross the persistence boundary as canonical strings so
     * binary floating-point values cannot become the source of truth for an
     * order, payment, discount, or reward calculation.
     */
    public static function round(int|float|string $amount): string
    {
        return self::fromMinor(self::toMinor($amount));
    }

    public static function multiply(int|float|string $amount, int $quantity): string
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('A monetary quantity cannot be negative.');
        }

        return self::fromMinor(self::multiplyMinor(self::toMinor($amount), $quantity));
    }

    public static function add(int|float|string ...$amounts): string
    {
        $total = 0;

        foreach ($amounts as $amount) {
            $total = self::addMinor($total, self::toMinor($amount));
        }

        return self::fromMinor($total);
    }

    public static function subtract(int|float|string $left, int|float|string $right): string
    {
        return self::fromMinor(self::addMinor(self::toMinor($left), -self::toMinor($right)));
    }

    public static function percentage(int|float|string $amount, int|float|string $percentage): string
    {
        // amountMinor * percentageMinor / 10,000 converts a percentage value
        // such as 12.50 into an exact fraction without decimal arithmetic.
        $product = self::multiplyMinor(self::toMinor($amount), self::toMinor($percentage));

        return self::fromMinor(self::roundDivision($product, 10_000));
    }

    public static function compare(int|float|string $left, int|float|string $right): int
    {
        return self::toMinor($left) <=> self::toMinor($right);
    }

    public static function toMinor(int|float|string $amount): int
    {
        $value = self::decimalString($amount);

        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d*))?$/D', $value, $parts)) {
            throw new InvalidArgumentException('Invalid monetary amount: '.$value);
        }

        $whole = ltrim($parts[2], '0');
        $whole = $whole === '' ? '0' : $whole;
        $maxWhole = (string) intdiv(PHP_INT_MAX, 100);

        if (strlen($whole) > strlen($maxWhole)
            || (strlen($whole) === strlen($maxWhole) && strcmp($whole, $maxWhole) > 0)) {
            throw new OverflowException('Monetary amount is too large for minor-unit arithmetic.');
        }

        $minor = ((int) $whole) * 100;
        $fraction = $parts[3] ?? '';
        $minor += (int) str_pad(substr($fraction, 0, 2), 2, '0');

        // Round half-up (away from zero for negative values) at the two-place
        // persistence boundary. Digits after the third do not change the rule.
        if (($fraction[2] ?? '0') >= '5') {
            $minor++;
        }

        if ($minor > PHP_INT_MAX) {
            throw new OverflowException('Monetary amount is too large for minor-unit arithmetic.');
        }

        return ($parts[1] ?? '') === '-' ? -$minor : $minor;
    }

    public static function fromMinor(int $minor): string
    {
        if ($minor === PHP_INT_MIN) {
            throw new OverflowException('Minor-unit amount is too small for formatting.');
        }

        $negative = $minor < 0;
        $absolute = abs($minor);
        $whole = intdiv($absolute, 100);
        $fraction = str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    private static function decimalString(int|float|string $amount): string
    {
        if (is_int($amount)) {
            return (string) $amount;
        }

        if (is_float($amount)) {
            if (! is_finite($amount)) {
                throw new InvalidArgumentException('A monetary amount must be finite.');
            }

            // A bounded textual representation removes the binary artifact
            // while retaining enough precision for the two-place boundary.
            return sprintf('%.14F', $amount);
        }

        $value = trim($amount);
        if ($value === '') {
            throw new InvalidArgumentException('A monetary amount cannot be empty.');
        }

        return $value;
    }

    private static function addMinor(int $left, int $right): int
    {
        if (($right > 0 && $left > PHP_INT_MAX - $right)
            || ($right < 0 && $left < PHP_INT_MIN - $right)) {
            throw new OverflowException('Minor-unit arithmetic overflowed.');
        }

        return $left + $right;
    }

    private static function multiplyMinor(int $left, int $right): int
    {
        if ($left === 0 || $right === 0) {
            return 0;
        }

        if (abs($left) > intdiv(PHP_INT_MAX, abs($right))) {
            throw new OverflowException('Minor-unit multiplication overflowed.');
        }

        return $left * $right;
    }

    private static function roundDivision(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new InvalidArgumentException('The division denominator must be positive.');
        }
        if ($numerator === 0) {
            return 0;
        }

        $negative = $numerator < 0;
        $absolute = abs($numerator);
        $rounded = intdiv($absolute + intdiv($denominator, 2), $denominator);

        return $negative ? -$rounded : $rounded;
    }
}
