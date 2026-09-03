<?php
namespace App\Providers;
use App\Models\{Address,AdminRecord,Category,Discount,Inquiry,InventoryMovement,Order,OrderItem,PaymentTransaction,Product,ProductMedia,ProductVariant,ReturnRequest,Review,RewardTransaction,ShippingMethod,Store,User,Wishlist};
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
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
    }
}
