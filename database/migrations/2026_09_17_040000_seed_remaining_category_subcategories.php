<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories') || ! Schema::hasColumn('categories', 'parent_id')) {
            return;
        }

        $catalogue = [
            'classic' => [
                'names' => ['Classic'],
                'slugs' => ['classic'],
                'description' => 'Classic Emerald Rozalia caps and hats built around timeless everyday silhouettes.',
                'children' => [
                    ['Classic Baseball Caps', 'classic-baseball-caps'],
                    ['Classic Snapbacks', 'classic-snapbacks'],
                    ['Classic Trucker Caps', 'classic-trucker-caps'],
                    ['Classic Dad Caps', 'classic-dad-caps'],
                    ['Classic 5-Panel Caps', 'classic-5-panel-caps'],
                    ['Classic 6-Panel Caps', 'classic-6-panel-caps'],
                    ['Classic Adjustable Caps', 'classic-adjustable-caps'],
                    ['Classic Casual Caps', 'classic-casual-caps'],
                    ['Classic Fashion Caps', 'classic-fashion-caps'],
                    ['Classic Premium Caps', 'classic-premium-caps'],
                ],
            ],
            'outdoor' => [
                'names' => ['Outdoor'],
                'slugs' => ['outdoor'],
                'description' => 'Outdoor Emerald Rozalia headwear for travel, countryside, sun and all-weather use.',
                'children' => [
                    ['Outdoor Bucket Hats', 'outdoor-bucket-hats'],
                    ['Hiking Caps', 'outdoor-hiking-caps'],
                    ['Fishing Hats', 'outdoor-fishing-hats'],
                    ['Sun Hats', 'outdoor-sun-hats'],
                    ['Boonie Hats', 'outdoor-boonie-hats'],
                    ['Packable Hats', 'outdoor-packable-hats'],
                    ['Wide Brim Hats', 'outdoor-wide-brim-hats'],
                    ['Adventure Caps', 'outdoor-adventure-caps'],
                    ['Water-Resistant Caps', 'outdoor-water-resistant-caps'],
                    ['Travel Caps', 'outdoor-travel-caps'],
                ],
            ],
            'winter' => [
                'names' => ['Winter'],
                'slugs' => ['winter'],
                'description' => 'Warm Emerald Rozalia winter headwear for cold-weather comfort and seasonal styling.',
                'children' => [
                    ['Cuffed Beanies', 'winter-cuffed-beanies'],
                    ['Ribbed Beanies', 'winter-ribbed-beanies'],
                    ['Pom Pom Beanies', 'winter-pom-pom-beanies'],
                    ['Thermal Beanies', 'winter-thermal-beanies'],
                    ['Fleece Hats', 'winter-fleece-hats'],
                    ['Wool Beanies', 'winter-wool-beanies'],
                    ['Knitted Hats', 'winter-knitted-hats'],
                    ['Earflap Hats', 'winter-earflap-hats'],
                    ['Winter Flat Caps', 'winter-flat-caps'],
                    ['Weather-Resistant Winter Caps', 'winter-weather-resistant-caps'],
                ],
            ],
            'sports' => [
                'names' => ['Sports', 'Sport'],
                'slugs' => ['sports', 'sport'],
                'description' => 'Emerald Rozalia sports headwear for clubs, supporters, teams, training and active use.',
                'children' => [
                    ['GAA Headwear', 'sports-gaa-headwear'],
                    ['Football Club Caps', 'sports-football-club-caps'],
                    ['Rugby Caps', 'sports-rugby-caps'],
                    ['Golf Caps', 'sports-golf-caps'],
                    ['Running Caps', 'sports-running-caps'],
                    ['Cycling Caps', 'sports-cycling-caps'],
                    ['Tennis Caps', 'sports-tennis-caps'],
                    ['Cricket Caps', 'sports-cricket-caps'],
                    ['Training Caps', 'sports-training-caps'],
                    ['Team Supporter Caps', 'sports-team-supporter-caps'],
                ],
            ],
            'workwear' => [
                'names' => ['Workwear'],
                'slugs' => ['workwear'],
                'description' => 'Emerald Rozalia branded and uniform headwear for teams, trades and professional workwear programmes.',
                'children' => [
                    ['Branded Work Caps', 'workwear-branded-work-caps'],
                    ['Corporate Uniform Caps', 'workwear-corporate-uniform-caps'],
                    ['Trade Caps', 'workwear-trade-caps'],
                    ['Construction Team Caps', 'workwear-construction-team-caps'],
                    ['Warehouse Team Caps', 'workwear-warehouse-team-caps'],
                    ['Hospitality Caps', 'workwear-hospitality-caps'],
                    ['Driver Caps', 'workwear-driver-caps'],
                    ['Maintenance Team Caps', 'workwear-maintenance-team-caps'],
                    ['Staff Beanies', 'workwear-staff-beanies'],
                    ['All-Weather Work Caps', 'workwear-all-weather-work-caps'],
                ],
            ],
            'kids' => [
                'names' => ['Kids'],
                'slugs' => ['kids'],
                'description' => 'Emerald Rozalia children and junior headwear across everyday, seasonal and sports styles.',
                'children' => [
                    ['Kids Baseball Caps', 'kids-baseball-caps'],
                    ['Kids Bucket Hats', 'kids-bucket-hats'],
                    ['Kids Beanies', 'kids-beanies'],
                    ['Kids Snapbacks', 'kids-snapbacks'],
                    ['Kids Sun Hats', 'kids-sun-hats'],
                    ['Kids Winter Hats', 'kids-winter-hats'],
                    ['Kids Sports Caps', 'kids-sports-caps'],
                    ['Kids Casual Caps', 'kids-casual-caps'],
                    ['Junior Flat Caps', 'kids-junior-flat-caps'],
                    ['School & Club Caps', 'kids-school-club-caps'],
                ],
            ],
            'costume' => [
                'names' => ['Costume', 'Costumes'],
                'slugs' => ['costume', 'costumes'],
                'description' => 'Emerald Rozalia costume and event headwear for themed, promotional, stage and celebration use.',
                'children' => [
                    ['Party Hats', 'costume-party-hats'],
                    ['Festival Hats', 'costume-festival-hats'],
                    ['Fancy Dress Hats', 'costume-fancy-dress-hats'],
                    ['Stage Hats', 'costume-stage-hats'],
                    ['Themed Hats', 'costume-themed-hats'],
                    ['Novelty Hats', 'costume-novelty-hats'],
                    ['Promotional Event Hats', 'costume-promotional-event-hats'],
                    ['Holiday Hats', 'costume-holiday-hats'],
                    ['Costume Caps', 'costume-caps'],
                    ['Costume Headwear', 'costume-headwear'],
                ],
            ],
        ];

        foreach ($catalogue as $group => $config) {
            $parent = $this->findParent($config['slugs'], $config['names']);
            if (! $parent) {
                continue;
            }

            $this->seedChildren(
                $parent,
                $group,
                $config['description'],
                $config['children'],
            );
        }
    }

    public function down(): void
    {
        // Intentional no-op. These categories become managed catalogue data and may
        // receive products or manual edits after deployment. Rollback must not remove
        // business data that may already be in active use.
    }

    /**
     * @param array<int, string> $slugs
     * @param array<int, string> $names
     */
    private function findParent(array $slugs, array $names): ?object
    {
        $base = DB::table('categories')->whereNull('parent_id');

        $parent = (clone $base)->whereIn('slug', $slugs)->orderBy('id')->first();
        if ($parent) {
            return $parent;
        }

        return (clone $base)->whereIn('name', $names)->orderBy('id')->first();
    }

    /**
     * @param array<int, array{0:string,1:string}> $children
     */
    private function seedChildren(object $parent, string $group, string $description, array $children): void
    {
        foreach ($children as $index => [$name, $slug]) {
            $existing = DB::table('categories')->where('slug', $slug)->first();

            $values = [
                'parent_id' => $parent->id,
                'name' => $name,
                'description' => $description,
                'is_active' => true,
                'sort_order' => $index + 1,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('categories', 'company_id')) {
                $values['company_id'] = $parent->company_id ?? null;
            }
            if (Schema::hasColumn('categories', 'status')) {
                $values['status'] = 'active';
            }
            if (Schema::hasColumn('categories', 'is_visible')) {
                $values['is_visible'] = true;
            }

            if ($existing) {
                if ((int) ($existing->parent_id ?? 0) === (int) $parent->id) {
                    DB::table('categories')->where('id', $existing->id)->update($values);
                }
                continue;
            }

            $insert = $values + [
                'slug' => $slug,
                'created_at' => now(),
            ];

            if (Schema::hasColumn('categories', 'public_uuid')) {
                $insert['public_uuid'] = (string) Str::uuid();
            }

            DB::table('categories')->insert($insert);
        }
    }
};
