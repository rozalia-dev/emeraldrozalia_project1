<?php

namespace App\Support;

final class CatalogProductTypes
{
    public const OPTIONS = [
        'beanies' => 'Beanies',
        'caps' => 'Caps',
        'hats' => 'Hats',
    ];

    public static function all(): array
    {
        return self::OPTIONS;
    }

    public static function isValid(?string $value): bool
    {
        return is_string($value) && array_key_exists(strtolower(trim($value)), self::OPTIONS);
    }

    public static function label(string $value): ?string
    {
        return self::OPTIONS[strtolower(trim($value))] ?? null;
    }
}
