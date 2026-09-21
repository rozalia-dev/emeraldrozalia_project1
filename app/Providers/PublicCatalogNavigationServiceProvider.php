<?php

namespace App\Providers;

use App\Http\Controllers\PublicCollectionController;
use App\Models\Category;
use App\Models\ProductCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

final class PublicCatalogNavigationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group(function (): void {
                Route::get('/collections/{collection:slug}', [PublicCollectionController::class, 'show'])
                    ->name('collection.show');
            });
        }

        View::composer('layouts.site', function ($view): void {
            $catalogNavCategories = Category::query()
                ->websiteVisible()
                ->whereNull('parent_id')
                ->withCount(['products' => fn ($query) => $query->published()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $catalogNavCollections = ProductCollection::query()
                ->withCount(['products' => fn ($query) => $query->published()])
                ->where('status', 'active')
                ->where('visibility', 'visible')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $view->with(compact('catalogNavCategories', 'catalogNavCollections'));
        });
    }
}
