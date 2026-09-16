<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class Chat24SevenAssistant
{
    public const QUICK_ACTIONS = [
        'New Arrivals',
        'Irish Heritage',
        'Irish Traditional',
        'GAA',
        'Fabric & Size',
        'Price',
        'Bulk Order',
        'Corporate Order',
        'Talk to a Person',
    ];

    public function answer(string $message, ?string $contextProductSlug = null, ?int $companyId = null): array
    {
        $message = trim($message);
        $normalized = Str::lower($message);
        $intent = $this->intent($normalized);

        if ($intent === 'human') {
            return $this->response(
                'I’ll hand this conversation to the Emerald Rozalia team. A team member can continue here as soon as they are available.',
                'human',
                true,
            );
        }

        $product = $contextProductSlug ? $this->productBySlug($contextProductSlug, $companyId) : null;
        if (! $product) {
            $product = $this->bestProductMatch($message, $companyId);
        }

        if ($product && in_array($intent, ['fabric', 'size', 'price', 'stock', 'moq', 'product'], true)) {
            return $this->answerForProduct($product, $intent);
        }

        if ($intent === 'new_arrivals') {
            return $this->productListResponse(
                $this->productQuery($companyId)->published()->where('is_new', true)->latest('published_at')->latest('id')->limit(5)->get(),
                'new_arrivals',
                'Here are the latest products currently marked as New Arrivals.',
            );
        }

        if (in_array($intent, ['irish_heritage', 'irish_traditional', 'gaa', 'uefa', 'fifa'], true)) {
            $term = match ($intent) {
                'irish_heritage' => 'irish heritage',
                'irish_traditional' => 'irish traditional',
                default => $intent,
            };
            $products = $this->productsForTopic($term, $companyId);
            $intro = match ($intent) {
                'irish_heritage' => 'Here are products currently matching our Irish Heritage range.',
                'irish_traditional' => 'Here are products currently matching our Irish Traditional range.',
                'gaa' => 'Here are GAA-related products currently listed in our catalogue.',
                'uefa' => 'Here are products currently matching UEFA-related catalogue terms. I will not describe any item as officially licensed unless that status is explicitly recorded in our system.',
                'fifa' => 'Here are products currently matching FIFA-related catalogue terms. I will not describe any item as officially licensed unless that status is explicitly recorded in our system.',
            };

            return $this->productListResponse($products, $intent, $intro, in_array($intent, ['uefa', 'fifa'], true) && $products->isEmpty());
        }

        if ($intent === 'bulk') {
            return $this->response(
                'For bulk orders I can help with product choice, quantity, branding method and required date. MOQ is product-specific, so I will only quote a minimum quantity when it is configured for that product. Tell me the product or style and your required quantity.',
                'bulk',
                false,
            );
        }

        if ($intent === 'corporate') {
            return $this->response(
                'For corporate orders I can help with hats/caps, quantity, embroidery or print requirements, delivery date and quotation preparation. Tell me the product/style and approximate quantity you need.',
                'corporate',
                false,
            );
        }

        if ($intent === 'franchise') {
            return $this->response(
                'I can help with Emerald Rozalia franchise information and can pass your enquiry to the franchise team. Tell me your preferred location and whether you are enquiring about a franchise or franchise retail store.',
                'franchise',
                false,
            );
        }

        if ($product) {
            return $this->answerForProduct($product, 'product');
        }

        return $this->response(
            'I can help 24/7 with Emerald Rozalia products, fabrics, sizes, current prices, stock, MOQ, New Arrivals, Irish Heritage, Irish Traditional, GAA-related products, bulk orders, corporate orders and franchise enquiries. Tell me what you would like to know, or choose one of the quick options below.',
            'general',
            false,
        );
    }

    public function greeting(): array
    {
        return $this->response(
            'Hi 👋 Welcome to Emerald Rozalia Limited. I’m the 24/7 product assistant. Ask me about products, fabrics, sizes, prices, stock, MOQ, New Arrivals, Irish Heritage, Irish Traditional, GAA-related products, bulk orders, corporate orders or franchise information.',
            'greeting',
            false,
        );
    }

    private function intent(string $message): string
    {
        if ($this->containsAny($message, ['human', 'person', 'agent', 'staff', 'talk to someone', 'speak to someone'])) return 'human';
        if ($this->containsAny($message, ['new arrival', 'new arrivals', 'latest', 'new products'])) return 'new_arrivals';
        if ($this->containsAny($message, ['irish heritage', 'heritage'])) return 'irish_heritage';
        if ($this->containsAny($message, ['irish traditional', 'traditional irish', 'flat hat'])) return 'irish_traditional';
        if ($this->containsAny($message, ['gaa', 'gaelic'])) return 'gaa';
        if ($this->containsAny($message, ['uefa'])) return 'uefa';
        if ($this->containsAny($message, ['fifa'])) return 'fifa';
        if ($this->containsAny($message, ['fabric', 'material', 'cotton', 'wool', 'polyester'])) return 'fabric';
        if ($this->containsAny($message, ['size', 'sizes', 'fit', 'fitting', 'adjustable'])) return 'size';
        if ($this->containsAny($message, ['price', 'cost', 'how much', '€', 'eur'])) return 'price';
        if ($this->containsAny($message, ['stock', 'available', 'availability', 'in stock'])) return 'stock';
        if ($this->containsAny($message, ['moq', 'minimum order', 'minimum quantity', 'minimum order quantity'])) return 'moq';
        if ($this->containsAny($message, ['bulk', 'wholesale', '100 pcs', '100 pieces', '200 pcs', '200 pieces'])) return 'bulk';
        if ($this->containsAny($message, ['corporate', 'company order', 'company logo'])) return 'corporate';
        if ($this->containsAny($message, ['franchise', 'store owner'])) return 'franchise';

        return 'product';
    }

    private function answerForProduct(Product $product, string $intent): array
    {
        $facts = $this->productFacts($product);
        $name = $facts['name'];

        $body = match ($intent) {
            'fabric' => $facts['material']
                ? "{$name} is recorded with material: {$facts['material']}."
                : "I don’t have a confirmed fabric/material specification recorded for {$name}. I can ask our product team rather than guess.",
            'size' => $facts['sizes'] !== []
                ? "{$name} is currently recorded with these size/fit options: ".implode(', ', $facts['sizes']).'.'
                : "I don’t have confirmed size options recorded for {$name}. I can ask our product team rather than guess.",
            'price' => $facts['price'] !== null
                ? "The current listed price for {$name} is {$facts['price']}. Bulk/corporate pricing may differ by quantity and branding."
                : "I don’t have a confirmed public price recorded for {$name}.",
            'stock' => $facts['stock'] !== null
                ? "The current recorded stock for {$name} is {$facts['stock']} unit(s)."
                : "I don’t have a confirmed stock quantity recorded for {$name}.",
            'moq' => $facts['moq'] !== null
                ? "The configured minimum order quantity for {$name} is {$facts['moq']} piece(s)."
                : "There is no confirmed MOQ recorded for {$name}. MOQ can differ for bulk, corporate, embroidery and custom orders, so I won’t invent a number. I can pass this to Sales for confirmation.",
            default => $this->productSummary($facts),
        };

        $requiresHuman = in_array($intent, ['fabric', 'size', 'price', 'stock', 'moq'], true)
            && (($intent === 'fabric' && ! $facts['material'])
                || ($intent === 'size' && $facts['sizes'] === [])
                || ($intent === 'price' && $facts['price'] === null)
                || ($intent === 'stock' && $facts['stock'] === null)
                || ($intent === 'moq' && $facts['moq'] === null));

        return $this->response($body, $intent, $requiresHuman, [$facts]);
    }

    private function productListResponse(Collection $products, string $intent, string $intro, bool $forceHuman = false): array
    {
        if ($products->isEmpty()) {
            return $this->response(
                $intro.' I could not find a currently published matching product, so I can ask the Emerald Rozalia team to confirm availability.',
                $intent,
                true,
            );
        }

        $facts = $products->map(fn (Product $product) => $this->productFacts($product))->values()->all();
        $names = collect($facts)->map(fn (array $row) => $row['name'].($row['price'] ? ' — '.$row['price'] : ''))->implode("\n• ");

        return $this->response($intro."\n• ".$names."\nSelect a product and I can check its fabric, sizes, price, stock or MOQ.", $intent, $forceHuman, $facts);
    }

    private function productsForTopic(string $term, ?int $companyId): Collection
    {
        $needle = '%'.str_replace(' ', '%', trim($term)).'%';

        return $this->productQuery($companyId)
            ->published()
            ->where(function (Builder $query) use ($needle): void {
                $query->where('name', 'like', $needle)
                    ->orWhere('description', 'like', $needle)
                    ->orWhere('material', 'like', $needle)
                    ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $needle))
                    ->orWhereHas('collections', function (Builder $collection) use ($needle): void {
                        $collection->where('status', 'active')
                            ->where('visibility', 'visible')
                            ->where(function (Builder $matching) use ($needle): void {
                                $matching->where('name', 'like', $needle)->orWhere('slug', 'like', $needle);
                            });
                    });
            })
            ->with(['variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->orderByDesc('is_new')
            ->orderBy('name')
            ->limit(5)
            ->get();
    }

    private function bestProductMatch(string $message, ?int $companyId): ?Product
    {
        $message = trim($message);
        if ($message === '') return null;

        $tokens = collect(preg_split('/[^\pL\pN]+/u', Str::lower($message)) ?: [])
            ->filter(fn (string $token) => mb_strlen($token) >= 3)
            ->reject(fn (string $token) => in_array($token, ['what', 'kind', 'price', 'size', 'fabric', 'material', 'stock', 'available', 'minimum', 'order', 'quantity', 'this', 'that', 'product', 'please'], true))
            ->unique()
            ->take(6)
            ->values();

        if ($tokens->isEmpty()) return null;

        $query = $this->productQuery($companyId)->published();
        $query->where(function (Builder $matching) use ($tokens): void {
            foreach ($tokens as $token) {
                $like = '%'.$token.'%';
                $matching->orWhere('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('material', 'like', $like);
            }
        });

        return $query->with(['variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])->first();
    }

    private function productBySlug(string $slug, ?int $companyId): ?Product
    {
        return $this->productQuery($companyId)
            ->published()
            ->where('slug', $slug)
            ->with(['variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->first();
    }

    private function productQuery(?int $companyId): Builder
    {
        $query = Product::withoutGlobalScopes();
        if ($companyId && $companyId > 0) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function productFacts(Product $product): array
    {
        $metadata = is_array($product->product_metadata) ? $product->product_metadata : [];
        $currency = strtoupper((string) ($metadata['currency'] ?? 'EUR'));
        $currencySymbol = match ($currency) {
            'GBP' => '£',
            'USD' => '$',
            default => '€',
        };
        $price = $product->price !== null ? $currencySymbol.number_format((float) $product->price, 2) : null;

        $sizes = collect(is_array($product->sizes) ? $product->sizes : []);
        foreach ($product->variants as $variant) {
            foreach ((array) $variant->option_values as $key => $value) {
                if (Str::contains(Str::lower((string) $key), ['size', 'fit']) && filled($value)) {
                    $sizes->push((string) $value);
                }
            }
        }

        $stock = $product->variants->isNotEmpty()
            ? $product->variants->sum(fn ($variant) => max(0, (int) $variant->stock))
            : ($product->stock !== null ? max(0, (int) $product->stock) : null);

        $moq = $metadata['minimum_order_quantity'] ?? $metadata['bulk_moq'] ?? $metadata['moq'] ?? null;
        $moq = is_numeric($moq) && (int) $moq > 0 ? (int) $moq : null;

        return [
            'name' => (string) $product->name,
            'slug' => (string) $product->slug,
            'sku' => (string) $product->sku,
            'material' => filled($product->material) ? (string) $product->material : null,
            'sizes' => $sizes->filter()->map(fn ($value) => trim((string) $value))->unique()->values()->all(),
            'price' => $price,
            'stock' => $stock,
            'moq' => $moq,
            'url' => route('product', ['product' => $product->slug]),
        ];
    }

    private function productSummary(array $facts): string
    {
        $parts = [$facts['name']];
        if ($facts['price']) $parts[] = 'Price: '.$facts['price'];
        if ($facts['material']) $parts[] = 'Material: '.$facts['material'];
        if ($facts['sizes'] !== []) $parts[] = 'Sizes/Fit: '.implode(', ', $facts['sizes']);
        if ($facts['stock'] !== null) $parts[] = 'Stock: '.$facts['stock'];
        if ($facts['moq'] !== null) $parts[] = 'MOQ: '.$facts['moq'];

        return implode("\n", $parts);
    }

    private function response(string $body, string $intent, bool $requiresHuman, array $products = []): array
    {
        return [
            'body' => $body,
            'intent' => $intent,
            'requires_human' => $requiresHuman,
            'products' => $products,
            'quick_actions' => self::QUICK_ACTIONS,
        ];
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (Str::contains($haystack, Str::lower($needle))) return true;
        }

        return false;
    }
}
