<?php

namespace App\Support;

final class CategoryIcons
{
    public static function options(): array
    {
        return [
            'grid' => 'All / Grid',
            'hat' => 'Hat / Traditional',
            'clover' => 'Heritage / Irish',
            'football' => 'Football / Sport',
            'globe' => 'Global / International',
            'gift' => 'Gift',
            'tag' => 'Accessory / Tag',
            'palette' => 'Customised / Design',
            'briefcase' => 'Corporate / Business',
            'package' => 'General Product',
            'star' => 'Featured',
            'store' => 'Retail / Store',
        ];
    }

    public static function names(): array
    {
        return array_keys(self::options());
    }

    public static function resolve(?string $icon, ?string $slug = null, ?string $name = null): string
    {
        if ($icon && in_array($icon, self::names(), true)) {
            return $icon;
        }

        $haystack = strtolower(trim(($slug ?? '').' '.($name ?? '')));

        return match (true) {
            str_contains($haystack, 'traditional') => 'hat',
            str_contains($haystack, 'heritage') => 'clover',
            str_contains($haystack, 'gaa') => 'football',
            str_contains($haystack, 'english'), str_contains($haystack, 'premier') => 'football',
            str_contains($haystack, 'uefa') => 'football',
            str_contains($haystack, 'fifa') => 'globe',
            str_contains($haystack, 'gift') => 'gift',
            str_contains($haystack, 'accessor') => 'tag',
            str_contains($haystack, 'custom') => 'palette',
            str_contains($haystack, 'corporate') => 'briefcase',
            default => 'package',
        };
    }
}
