<?php
namespace App\Providers;
use App\Http\Controllers\Admin\CpanelThemeController;
use App\Models\{Address,AdminRecord,Category,ContentPage,Discount,Inquiry,InventoryMovement,Order,OrderItem,PaymentTransaction,Product,ProductMedia,ProductVariant,ReturnRequest,Review,RewardTransaction,ShippingMethod,Store,User,Wishlist};
use App\Services\{CpanelThemeVersionService, PublishedSiteSettings, SiteLayoutVersionService};
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
class AppServiceProvider extends ServiceProvider {
    public function register(): void {}

    public function boot(): void
    {
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

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

        Route::middleware(['web', 'auth', 'admin'])
            ->prefix('admin/settings/cpanel-theme')
            ->name('admin.settings.cpanel-theme.')
            ->group(function (): void {
                Route::get('/', [CpanelThemeController::class, 'index'])->name('index');
                Route::post('/', [CpanelThemeController::class, 'store'])->name('store');
                Route::patch('/{theme}', [CpanelThemeController::class, 'update'])->whereUuid('theme')->name('update');
                Route::post('/{theme}/{action}', [CpanelThemeController::class, 'action'])
                    ->whereUuid('theme')
                    ->where('action', 'validate|submit|approve|activate|disable|rollback')
                    ->name('action');
            });

        View::composer('layouts.site', function ($view): void {
            $siteSettings = app(PublishedSiteSettings::class)->forCompany();
            $previewLayout = $view->getData()['siteLayoutPreview'] ?? null;
            $siteLayout = is_array($previewLayout)
                ? $previewLayout
                : app(SiteLayoutVersionService::class)->publicSnapshot();
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

            $view->with(['footerPages' => $footerPages, 'siteSettings' => $siteSettings, 'siteLayout' => $siteLayout]);
        });

        View::composer('layouts.admin', function (): void {
            $cpanelThemeSnapshot = app(CpanelThemeVersionService::class)->activeSnapshot();

            View::startPush('styles', view('admin.partials.cpanel-theme-runtime', [
                'cpanelThemeSnapshot' => $cpanelThemeSnapshot,
            ])->render());
            View::startPush('scripts', view('admin.partials.cpanel-theme-nav')->render());
        });
    }
}
