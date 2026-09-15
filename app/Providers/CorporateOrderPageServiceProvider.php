<?php

namespace App\Providers;

use App\Models\{ContentPage, Product};
use App\Services\PublicMediaResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View as BladeView;

class CorporateOrderPageServiceProvider extends ServiceProvider
{
    public function boot(PublicMediaResolver $media): void
    {
        View::composer('site.corporate-order', function (BladeView $view) use ($media): void {
            $locale = app()->getLocale();
            $page = ContentPage::query()
                ->where('slug', 'corporate-orders')
                ->where('status', 'published')
                ->where('locale', $locale)
                ->where(function ($query): void {
                    $query->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now());
                })
                ->with(['sections' => function ($query) use ($locale): void {
                    $query->where('visible', true)
                        ->where(function ($localeQuery) use ($locale): void {
                            $localeQuery->whereNull('locale')->orWhere('locale', $locale);
                        });
                }])
                ->first();

            $sections = $page?->sections ?? collect();
            $heroSection = $sections->first(fn ($section): bool => $section->type === 'hero');
            $trustedSection = $sections->first(fn ($section): bool => $this->labelContains($section->label, ['trusted', 'client', 'organisation', 'organization']));

            $productMedia = Product::query()
                ->published()
                ->whereHas('media', fn ($query) => $query->where('type', 'image'))
                ->with(['media' => fn ($query) => $query->where('type', 'image')])
                ->orderByDesc('is_new')
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get()
                ->map(fn (Product $product): ?array => $media->forProduct($product, 'image'))
                ->filter()
                ->values();

            $heroMedia = $media->forUuid($heroSection?->media_uuid, 'Emerald Rozalia corporate headwear')
                ?? $productMedia->first();

            $offerDefinitions = collect([
                'embroidered' => ['embroidered', 'embroidery'],
                'printed' => ['printed', 'print'],
                'custom' => ['custom', 'bespoke'],
                'quality' => ['premium quality', 'quality'],
            ]);

            $offerMedia = $offerDefinitions->mapWithKeys(function (array $needles, string $key) use ($sections, $media, $productMedia): array {
                $section = $sections->first(fn ($section): bool => $this->labelContains($section->label, $needles));
                $resolved = $media->forUuid($section?->media_uuid, 'Emerald Rozalia '.$key.' corporate headwear');
                $fallbackIndex = match ($key) {
                    'embroidered' => 0,
                    'printed' => 1,
                    'custom' => 2,
                    default => 3,
                };

                return [$key => $resolved ?? $productMedia->get($fallbackIndex)];
            })->all();

            $trustedMedia = collect();
            if ($trustedSection) {
                $trustedMedia = $trustedMedia->push($trustedSection->media_uuid);
                foreach ((array) data_get($trustedSection->settings, 'items', []) as $item) {
                    if (is_array($item)) {
                        $trustedMedia->push($item['media_uuid'] ?? null);
                    }
                }
            }

            $trustedMedia = collect($media->forUuids($trustedMedia))
                ->values()
                ->take(8)
                ->values();

            $trustedNames = collect((array) data_get($trustedSection?->settings, 'clients', []))
                ->map(fn ($client) => is_array($client) ? ($client['name'] ?? null) : $client)
                ->filter(fn ($name): bool => is_string($name) && trim($name) !== '')
                ->map(fn (string $name): string => Str::limit(trim($name), 80, ''))
                ->take(12)
                ->values();

            $view->with([
                'managedPage' => $page,
                'corporatePage' => $page,
                'corporateSections' => $sections,
                'corporateHeroMedia' => $heroMedia,
                'corporateOfferMedia' => $offerMedia,
                'corporateTrustedMedia' => $trustedMedia,
                'corporateTrustedNames' => $trustedNames,
                'corporateIdempotencyKey' => old('idempotency_key') ?: (string) Str::uuid(),
            ]);
        });
    }

    private function labelContains(?string $label, array $needles): bool
    {
        $label = Str::lower((string) $label);

        return collect($needles)->contains(fn (string $needle): bool => Str::contains($label, Str::lower($needle)));
    }
}
