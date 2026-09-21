<?php

namespace App\Support;

final class CatalogStyles
{
    public const OPTIONS = [
        'baseball-cap' => 'Baseball Cap',
        'bucket-hats' => 'Bucket Hats',
        'beanie' => 'Beanie',
        'kids' => 'Kids',
        'summer' => 'Summer',
        'winter' => 'Winter',
        'dad' => 'Dad',
        'cadet-cap' => 'Cadet Cap',
        'walking-cap' => 'Walking Cap',
        'patchwork-cap' => 'Patchwork Cap',
        'golf' => 'Golf',
        'fisherman' => 'Fisherman',
        'farmer' => 'Farmer',
        'cycling' => 'Cycling',
        'spring' => 'Spring',
        'new-arrival' => 'New Arrival',
        'gift-for-her' => 'Gift for Her',
        'back-to-college' => 'Back to College',
        'back-to-school' => 'Back to School',
        'back-to-university' => 'Back to University',
        'customise' => 'Customise',
        'bulk-gift' => 'Bulk Gift',
        'corporate-gift' => 'Corporate Gift',
        'wedding' => 'Wedding',
        'party' => 'Party',
    ];

    public static function all(): array
    {
        return self::OPTIONS;
    }
}
