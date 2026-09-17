<?php
namespace App\Providers;
use App\Models\{Address,AdminRecord,Category,ContentPage,Discount,Inquiry,InventoryMovement,Order,OrderItem,PaymentTransaction,Product,ProductMedia,ProductVariant,ReturnRequest,Review,RewardTransaction,ShippingMethod,Store,User,Wishlist};
use App\Services\{CpanelThemeVersionService, PublishedSiteSettings, SiteLayoutVersionService};
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
class AppServiceProvider extends ServiceProvider {
    public function register(): void {}

    public function boot(): void
    {
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $minutes = (int) config('auth.verification.expire', 60);

            return (new MailMessage)
                ->subject('Verify your Emerald Rozalia email address')
                ->greeting('Welcome to Emerald Rozalia')
                ->line('Please verify your email address to activate your customer account.')
                ->action('VERIFY EMAIL ADDRESS', $url)
                ->line("This secure verification link expires in {$minutes} minutes.")
                ->line('If you did not create this account, no action is required.');
        });

        ProductMedia::creating(function (ProductMedia $record): void {
            $record->uuid ??= (string) Str::uuid();
        });
        foreach ([
            User::class, Category::class, Product::class, Order::class,
            OrderItem::class, Inquiry::class, Store::class, ProductVariant::class,
            Address::class, Wishlist::class, Review::class, ReturnRequest::class,
            RewardTransaction::class, PaymentTransaction::class, InventoryMovement::class,
            ShippingMethod::class, Discount::class, AdminRecord::class,
        ] as $model) {
            $model::creating(function ($record): void {
                $record->public_uuid ??= (string) Str::uuid();
            });
        }

        Event::listen(\App\Events\SalesQuoteConverted::class, [\App\Services\AutomationEventBridge::class, 'salesQuoteConverted']);
        Event::listen(\App\Events\CommunicationConversationChanged::class, [\App\Services\AutomationEventBridge::class, 'conversationChanged']);
        Event::listen(\App\Events\ApprovalRequestChanged::class, [\App\Services\AutomationEventBridge::class, 'approvalChanged']);
        Event::listen(\App\Events\CommunicationTemplateChanged::class, [\App\Services\AutomationEventBridge::class, 'templateChanged']);
        Event::listen(\App\Events\FranchiseStoreLifecycleChanged::class, [\App\Services\AutomationEventBridge::class, 'franchiseStoreChanged']);

        View::composer('layouts.site', function ($view): void {
            $siteSettings = app(PublishedSiteSettings::class)->forCompany();
            $previewLayout = $view->getData()['siteLayoutPreview'] ?? null;
            $siteLayout = is_array($previewLayout)
                ? $previewLayout
                : app(SiteLayoutVersionService::class)->publicSnapshot();
            $siteLayout = $this->withCatalogueMenu($siteLayout);
            $footerPages = ContentPage::query()
                ->where('locale', app()->getLocale())
                ->where('status', 'published')
                ->where(fn ($query) => $query->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now()))
                ->get()
                ->filter(fn (ContentPage $page): bool => $page->isPublic()
                    && $page->shouldShowInFooter()
                    && (! $page->requiresLogin() || auth()->check()))
                ->take(8)
                ->values();
            $catalogTree = Category::query()
                ->websiteVisible()
                ->whereNull('parent_id')
                ->with('childrenRecursive')
                ->withCount('products')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            if (request()->getPathInfo() === '/') {
                $catalogNavCategories = $catalogTree->values();
            } else {
                $catalogNavCategories = collect();
                $flattenCatalog = function ($nodes, int $depth = 0) use (&$flattenCatalog, $catalogNavCategories): void {
                    foreach ($nodes as $node) {
                        $displayNode = clone $node;
                        if ($depth > 0) {
                            $displayNode->setAttribute('name', str_repeat('↳ ', $depth).$node->name);
                        }
                        $catalogNavCategories->push($displayNode);
                        $children = $node->childrenRecursive ?? collect();
                        if ($children->isNotEmpty()) {
                            $flattenCatalog($children, $depth + 1);
                        }
                    }
                };
                $flattenCatalog($catalogTree);
            }

            $view->with([
                'footerPages' => $footerPages,
                'siteSettings' => $siteSettings,
                'siteLayout' => $siteLayout,
                'catalogNavCategories' => $catalogNavCategories,
            ]);
        });

        View::composer('layouts.admin', function (): void {
            $cpanelThemeSnapshot = app(CpanelThemeVersionService::class)->activeSnapshot();

            View::startPush('styles', view('admin.partials.cpanel-theme-runtime', [
                'cpanelThemeSnapshot' => $cpanelThemeSnapshot,
            ])->render());
            View::startPush('scripts', view('admin.partials.cpanel-theme-nav')->render());
        });
    }

    private function withCatalogueMenu(array $siteLayout): array
    {
        $menu = array_values((array) data_get($siteLayout, 'regions.header.primary_menu', []));
        $hasCatalogue = collect($menu)->contains(function ($item): bool {
            $label = strtoupper(trim((string) data_get($item, 'label', '')));
            $href = data_get($item, 'href');
            $path = is_string($href) ? (parse_url($href, PHP_URL_PATH) ?: $href) : '';

            return $label === 'CATALOGUE' || $path === '/product-catalogue';
        });

        if ($hasCatalogue) {
            return $siteLayout;
        }

        $catalogueItem = ['label' => 'CATALOGUE', 'href' => '/product-catalogue'];
        $insertAt = null;

        foreach ($menu as $index => $item) {
            $label = strtoupper(trim((string) data_get($item, 'label', '')));
            $href = data_get($item, 'href');
            $path = is_string($href) ? (parse_url($href, PHP_URL_PATH) ?: $href) : '';
            if ($label === 'COLLECTIONS' || $path === '/collections') {
                $insertAt = $index + 1;
                break;
            }
        }

        if ($insertAt === null) {
            $menu[] = $catalogueItem;
        } else {
            array_splice($menu, $insertAt, 0, [$catalogueItem]);
        }

        data_set($siteLayout, 'regions.header.primary_menu', $menu);

        return $siteLayout;
    }
}
