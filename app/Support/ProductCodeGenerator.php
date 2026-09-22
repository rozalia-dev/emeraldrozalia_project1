<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Str;

final class ProductCodeGenerator
{
    private const TAXONOMY_CODES = [
        'traditional' => 'TRD',
        'heritage' => 'HER',
        'gaa' => 'GAA',
        'english' => 'ENG',
        'uefa' => 'UEFA',
        'fifa' => 'FIFA',
        'gift' => 'GFT',
        'accessory' => 'ACC',
        'customised' => 'CUS',
        'corporate' => 'COR',
    ];

    private const FAMILY_CODES = [
        'caps' => 'CAP',
        'hats' => 'HAT',
        'beanies' => 'BEA',
        'beanie' => 'BEA',
    ];

    public static function temporarySku(): string
    {
        return 'ER-TMP-'.strtoupper(Str::random(16));
    }

    public static function sku(int $productId, Category $category): string
    {
        [$taxonomy, $family] = self::categoryContext($category);

        $taxonomyCode = self::TAXONOMY_CODES[$taxonomy]
            ?? strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $taxonomy ?: 'GEN'), 0, 5))
            ?: 'GEN';

        $familyCode = self::FAMILY_CODES[$family]
            ?? strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $family ?: 'PRD'), 0, 4))
            ?: 'PRD';

        return sprintf('ER-%s-%s-%06d', $taxonomyCode, $familyCode, $productId);
    }

    public static function suggestedHsCode(Category $category, ?string $style = null): ?string
    {
        [, $family] = self::categoryContext($category);

        if (in_array($family, ['caps', 'hats', 'beanies', 'beanie'], true)) {
            return '650500';
        }

        $style = strtolower(trim((string) $style));
        if ($style !== '' && in_array($style, [
            'baseball-cap', 'bucket-hats', 'beanie', 'kids', 'summer', 'winter', 'dad',
            'cadet-cap', 'walking-cap', 'patchwork-cap', 'golf', 'fisherman', 'farmer',
            'cycling', 'spring', 'new-arrival', 'gift-for-her', 'back-to-college',
            'back-to-school', 'back-to-university', 'customise', 'bulk-gift',
            'corporate-gift', 'wedding', 'party',
        ], true)) {
            return '650500';
        }

        return null;
    }

    private static function categoryContext(Category $category): array
    {
        $taxonomy = strtolower((string) ($category->taxonomy_type ?? ''));
        $family = strtolower((string) ($category->product_type ?? ''));

        $cursor = $category;
        while ($cursor->parent_id) {
            $cursor = $cursor->parent()->first() ?? $cursor;
            if ($cursor->is($category)) {
                break;
            }

            if ($taxonomy === '' && filled($cursor->taxonomy_type)) {
                $taxonomy = strtolower((string) $cursor->taxonomy_type);
            }
            if ($family === '' && filled($cursor->product_type)) {
                $family = strtolower((string) $cursor->product_type);
            }
        }

        if ($taxonomy === '') {
            $taxonomy = strtolower((string) ($cursor->slug ?? 'general'));
        }

        if ($family === '') {
            $name = strtolower((string) $category->name);
            $family = str_contains($name, 'beanie') ? 'beanies'
                : (str_contains($name, 'hat') ? 'hats'
                    : (str_contains($name, 'cap') ? 'caps' : 'product'));
        }

        return [$taxonomy, $family];
    }
}
