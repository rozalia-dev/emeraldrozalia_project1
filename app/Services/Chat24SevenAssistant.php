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
        'Franchise Application',
        'Franchise Retail Store',
        'Franchise Requirements',
        'Book Appointment',
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

        if ($intent === 'bulk') {
            $products = [];
            $body = 'For bulk orders, please use our Bulk Order form so the team receives your product/style, quantity, branding and required date in the correct workflow.';
            if ($product) {
                $facts = $this->productFacts($product);
                $products[] = $facts;
                $body .= "\n\nI found {$facts['name']} in the current catalogue.";
                if ($facts['moq'] !== null) {
                    $body .= ' Its configured MOQ is '.$facts['moq'].' piece(s).';
                } else {
                    $body .= ' No product-specific MOQ is currently recorded, so I will not invent one.';
                }
            } else {
                $body .= '\n\nI could not confirm an exact currently published product match from your message. You can still submit the quantity and requested style as a custom/bulk enquiry.';
            }

            return $this->response(
                $body,
                'bulk',
                false,
                $products,
                $this->actionsFor('bulk'),
            );
        }

        if ($intent === 'corporate') {
            $products = [];
            $body = 'For corporate orders, use our Corporate Order form to send the product/style, quantity, logo/branding method and required delivery date. The request goes into the Communication Center for quotation follow-up.';
            if ($product) {
                $facts = $this->productFacts($product);
                $products[] = $facts;
                $body .= "\n\nI found {$facts['name']} in the current catalogue and included it below for reference.";
            }

            return $this->response(
                $body,
                'corporate',
                false,
                $products,
                $this->actionsFor('corporate'),
            );
        }

        if ($intent === 'franchise_application') {
            return $this->response(
                'You can submit an Emerald Rozalia franchise application online. The form collects your contact details, preferred city/region and business background so the franchise team can review it and follow up with you.',
                'franchise_application',
                false,
                [],
                $this->actionsFor('franchise_application'),
            );
        }

        if ($intent === 'franchise_retail') {
            return $this->response(
                'If you want to own and operate an Emerald Rozalia franchise retail store, use the retail-store application link below. It opens the store-owner/franchise application workflow for the franchise team.',
                'franchise_retail',
                false,
                [],
                $this->actionsFor('franchise_retail'),
            );
        }

        if ($intent === 'franchise_requirements') {
            return $this->response(
                'I can show you the Franchise Requirements & Document Checklist. It explains the initial applicant information and the supporting documents that may be requested during verification. Exact document requirements can vary, so the franchise team confirms them before sensitive documents are requested.',
                'franchise_requirements',
                false,
                [],
                $this->actionsFor('franchise_requirements'),
            );
        }

        if ($intent === 'appointment') {
            return $this->response(
                'You can book a conversation with the Emerald Rozalia team using the calendar. Choose an available weekday date and Irish-time slot, add your contact details and submit the meeting request.',
                'appointment',
                false,
                [],
                $this->actionsFor('appointment'),
            );
        }

        $factIntents = ['fabric', 'size', 'fabric_size', 'price', 'stock', 'moq'];
        if ($product && in_array($intent, [...$factIntents, 'product'], true)) {
            return $this->answerForProduct($product, $intent);
        }

        if (! $product && in_array($intent, $factIntents, true)) {
            return $this->response(
                $this->missingProductPrompt($intent),
                $intent,
                false,
            );
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

            return $this->productListResponse(
                $products,
                $intent,
                $intro,
                in_array($intent, ['uefa', 'fifa'], true) && $products->isEmpty(),
            );
        }

        if ($product) {
            return $this->answerForProduct($product, 'product');
        }

        return $this->response(
            'I can help 24/7 with Emerald Rozalia products, fabrics, sizes, current prices, stock, MOQ, New Arrivals, Irish Heritage, Irish Traditional, GAA-related products, bulk and corporate order forms, franchise applications, franchise retail stores, franchise requirements and appointment booking. Choose an option below or tell me what you need.',
            'general',
            false,
        );
    }

    public function greeting(): array
    {
        return $this->response(
            'Hi 👋 Welcome to Emerald Rozalia Limited. I’m the 24/7 product assistant. Ask about products, fabrics, sizes, prices, stock, MOQ, New Arrivals, Irish Heritage, Irish Traditional, GAA-related products, bulk/corporate orders, franchise applications, franchise retail stores, requirements or booking an appointment.',
            'greeting',
            false,
        );
    }

    private function intent(string $message): string
    {
        if ($this->containsAny($message, ['human', 'person', 'agent', 'staff', 'talk to someone', 'speak to someone'])) return 'human';
        if ($this->containsAny($message, ['book appointment', 'appointment', 'schedule meeting', 'book meeting', 'calendar', 'choose date', 'choose time'])) return 'appointment';
        if ($this->containsAny($message, ['franchise requirement', 'franchise requirements', 'requirement document', 'requirements document', 'document checklist', 'documents required'])) return 'franchise_requirements';
        if ($this->containsAny($message, ['franchise retail', 'retail store', 'store owner', 'own a store', 'be a store owner'])) return 'franchise_retail';
        if ($this->containsAny($message, ['franchise application', 'apply franchise', 'apply for franchise', 'franchise apply'])) return 'franchise_application';
        if ($this->containsAny($message, ['bulk', 'wholesale']) || $this->hasBulkQuantity($message)) return 'bulk';
        if ($this->containsAny($message, ['corporate', 'company order', 'company logo', 'staff uniform'])) return 'corporate';
        if ($this->containsAny($message, ['franchise'])) return 'franchise_application';
        if ($this->containsAny($message, ['new arrival', 'new arrivals', 'latest', 'new products'])) return 'new_arrivals';
        if ($this->containsAny($message, ['irish heritage', 'heritage'])) return 'irish_heritage';
        if ($this->containsAny($message, ['irish traditional', 'traditional irish', 'flat hat'])) return 'irish_traditional';
        if ($this->containsAny($message, ['gaa', 'gaelic'])) return 'gaa';
        if ($this->containsAny($message, ['uefa'])) return 'uefa';
        if ($this->containsAny($message, ['fifa'])) return 'fifa';

        $asksFabric = $this->containsAny($message, ['fabric', 'material', 'cotton', 'wool', 'polyester']);
        $asksSize = $this->containsAny($message, ['size', 'sizes', 'fit', 'fitting', 'adjustable']);
        if ($asksFabric && $asksSize) return 'fabric_size';
        if ($asksFabric) return 'fabric';
        if ($asksSize) return 'size';

        if ($this->containsAny($message, ['price', 'cost', 'how much', '€', 'eur'])) return 'price';
        if ($this->containsAny($message, ['stock', 'available', 'availability', 'in stock'])) return 'stock';
        if ($this->containsAny($message, ['moq', 'minimum order', 'minimum quantity', 'minimum order quantity'])) return 'moq';

        return 'product';
    }

    private function hasBulkQuantity(string $message): bool
    {
        if (! preg_match_all('/\b([0-9][0-9,]*)\s*(?:pcs?|pieces?|units?)\b/i', $message, $matches)) {
            return false;
        }

        foreach ($matches[1] as $raw) {
            $quantity = (int) str_replace(',', '', (string) $raw);
            if ($quantity >= 50) return true;
        }

        return false;
    }

    private function actionsFor(string $intent): array
    {
        return match ($intent) {
            'bulk' => [[
                'label' => 'Fill Bulk Order Form',
                'url' => route('bulk.orders').'#bulk-quote',
            ]],
            'corporate' => [[
                'label' => 'Fill Corporate Order Form',
                'url' => route('corporate.orders').'#corporate-quote',
            ]],
            'franchise_application' => [[
                'label' => 'Apply for Franchise',
                'url' => route('franchise').'#franchise-enquiry',
            ], [
                'label' => 'View Requirements',
                'url' => route('franchise.requirements'),
            ]],
            'franchise_retail' => [[
                'label' => 'Apply for Franchise Retail Store',
                'url' => route('store.owner').'#franchise-enquiry',
            ], [
                'label' => 'View Requirements',
                'url' => route('franchise.requirements'),
            ]],
            'franchise_requirements' => [[
                'label' => 'Open Requirements & Document Checklist',
                'url' => route('franchise.requirements'),
            ], [
                'label' => 'Apply for Franchise',
                'url' => route('franchise').'#franchise-enquiry',
            ]],
            'appointment' => [[
                'label' => 'Choose Date & Time',
                'url' => route('contact').'#contact-schedule',
            ]],
            default => [],
        };
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
            'fabric_size' => $this->fabricAndSizeAnswer($facts),
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

        $requiresHuman = match ($intent) {
            'fabric' => ! $facts['material'],
            'size' => $facts['sizes'] === [],
            'fabric_size' => ! $facts['material'] || $facts['sizes'] === [],
            'price' => $facts['price'] === null,
            'stock' => $facts['stock'] === null,
            'moq' => $facts['moq'] === null,
            default => false,
        };

        return $this->response($body, $intent, $requiresHuman, [$facts]);
    }

    private function fabricAndSizeAnswer(array $facts): string
    {
        $parts = [$facts['name'].':'];
        $parts[] = $facts['material']
            ? 'Material: '.$facts['material']
            : 'Material: not yet confirmed in our catalogue.';
        $parts[] = $facts['sizes'] !== []
            ? 'Sizes/Fit: '.implode(', ', $facts['sizes'])
            : 'Sizes/Fit: not yet confirmed in our catalogue.';

        if (! $facts['material'] || $facts['sizes'] === []) {
            $parts[] = 'I can ask our product team to confirm the missing detail rather than guess.';
        }

        return implode("\n", $parts);
    }

    private function missingProductPrompt(string $intent): string
    {
        $topic = match ($intent) {
            'fabric' => 'fabric/material',
            'size' => 'size/fit',
            'fabric_size' => 'fabric and size',
            'price' => 'current price',
            'stock' => 'current stock',
            'moq' => 'minimum order quantity (MOQ)',
            default => 'product details',
        };

        return "I can check the {$topic}, but I need the product first. Tell me the product name/SKU, or open a product page and ask the same question again.";
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
            ->reject(fn (string $token) => in_array($token, [
                'what', 'kind', 'price', 'size', 'sizes', 'fabric', 'material', 'stock', 'available',
                'minimum', 'order', 'quantity', 'this', 'that', 'product', 'please', 'cost', 'much',
                'bulk', 'wholesale', 'pieces', 'piece', 'units', 'unit', 'corporate', 'franchise',
            ], true))
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
        $query = Product::withoutGlobalScope('tenant');
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

    private function response(string $body, string $intent, bool $requiresHuman, array $products = [], array $actions = []): array
    {
        return [
            'body' => $body,
            'intent' => $intent,
            'requires_human' => $requiresHuman,
            'products' => $products,
            'actions' => $actions,
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
