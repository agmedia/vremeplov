<?php

namespace App\Providers;

use App\Helpers\Helper;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Page;
use App\Models\User;
use App\Models\Front\Catalog\Product;
use App\Models\Back\Marketing\Review;
use App\Models\Back\Marketing\Wishlist;
use App\Services\GoogleLoginSettingsService;
use App\Services\StorefrontContentSettingsService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Schema::defaultStringLength(191);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer('front.layouts.modals.login', function ($view) {
            $view->with('googleLoginEnabled', Cache::remember(
                GoogleLoginSettingsService::ENABLED_CACHE_KEY,
                now()->addMinutes(5),
                fn () => app(GoogleLoginSettingsService::class)->enabled()
            ));
        });

        View::composer([
            'front.layouts.app',
            'errors.container',
        ], function ($view) {
            $view->with('storefrontContent', Cache::remember(
                StorefrontContentSettingsService::CACHE_KEY,
                now()->addMinutes(5),
                fn () => app(StorefrontContentSettingsService::class)->get()
            ));
        });

        View::composer('front.layouts.partials.footer', function ($view) {
            $view->with([
                'uvjeti_kupnje' => $this->purchaseTerms(),
                'hasCatalogActions' => $this->catalogActionsAvailable(),
            ]);
        });

        View::composer('back.layouts.partials.topbar', function ($view) {
            $wishlistReadyCount = $this->tableAvailableForView('wishlist') && $this->tableAvailableForView('products')
                ? Wishlist::query()->readyToSend()->count()
                : 0;
            $pendingCommentCount = $this->tableAvailableForView('reviews')
                ? Review::query()->where('status', 0)->count()
                : 0;

            $view->with(compact('wishlistReadyCount', 'pendingCommentCount'));
        });

        View::composer('front.layouts.partials.header', function ($view) {
            $mobileNavigationGroups = collect();
            $mobileNavigationBookCategories = collect();
            $navigationGroupProductCounts = collect();
            $hasCatalogActions = $this->catalogActionsAvailable();

            if ($this->tableAvailableForView('categories')) {
                $mobileNavigationGroups = Category::getGroups();
                $bookNavigationGroup = $mobileNavigationGroups->firstWhere('slug', 'knjige');
                $bookNavigationGroupSlug = $bookNavigationGroup->slug ?? 'knjige';
                $mobileNavigationBookCategories = Helper::resolveCache('categories')->remember(
                    'mobile-navigation.books.v4',
                    config('cache.life'),
                    function () use ($bookNavigationGroupSlug) {
                        return Category::query()
                            ->active()
                            ->topList($bookNavigationGroupSlug)
                            ->orderBy('title')
                            ->select('id', 'title', 'group', 'slug', 'sort_order')
                            ->withCount('products')
                            ->with(['subcategories' => function ($query) {
                                $query->select('id', 'parent_id', 'title', 'group', 'slug', 'sort_order')
                                    ->withCount('products')
                                    ->orderBy('title');
                            }])
                            ->get()
                            ->map(function (Category $category) {
                                $category->setRelation(
                                    'subcategories',
                                    $category->subcategories
                                        ->filter(fn (Category $subcategory) => (int) $subcategory->products_count > 0)
                                        ->values()
                                );

                                return $category;
                            })
                            ->filter(fn (Category $category) => (int) $category->products_count > 0 || $category->subcategories->isNotEmpty())
                            ->values();
                    }
                );
            }

            if ($this->tableAvailableForView('products')) {
                $navigationGroupProductCounts = Cache::remember(
                    'catalog.navigation.group-counts.v1',
                    now()->addMinutes(5),
                    function () {
                        return Product::query()
                            ->active()
                            ->hasStock()
                            ->select('group')
                            ->selectRaw('COUNT(*) as aggregate')
                            ->groupBy('group')
                            ->pluck('aggregate', 'group')
                            ->map(fn ($count) => (int) $count);
                    }
                );

                $mobileNavigationGroups = $mobileNavigationGroups
                    ->filter(function ($navigationGroup) use ($navigationGroupProductCounts) {
                        return (int) (
                            $navigationGroupProductCounts->get($navigationGroup->slug)
                            ?? $navigationGroupProductCounts->get($navigationGroup->title)
                            ?? 0
                        ) > 0;
                    })
                    ->values();
            }

            $view->with(compact(
                'mobileNavigationGroups',
                'mobileNavigationBookCategories',
                'navigationGroupProductCounts',
                'hasCatalogActions'
            ));
        });

        /*$nacini_placanja = Page::where('subgroup', 'Načini plaćanja')->get();
        View::share('nacini_placanja', $nacini_placanja);

        $products = Product::active()->hasStock()->count();
        View::share('products', $products);

        $users = User::count();
        View::share('users', $users);

        $knjige = Category::active()->topList(Helper::categoryGroupPath(true))->sortByName()->select('id', 'title', 'group', 'slug')->get();
        View::share('knjige', $knjige);

        $kategorijefeatured = Category::active()->where('image', '!=', 'media/avatars/avatar0.jpg')->sortByName()->select('id', 'image', 'title', 'group', 'slug')->get();
        View::share('kategorijefeatured', $kategorijefeatured);

        $zemljovidi_vedute = Category::active()->topList('Zemljovidi i vedute')->select('id', 'title', 'group', 'slug')->sortByName()->get();
        View::share('zemljovidi_vedute', $zemljovidi_vedute);*/

        Paginator::useBootstrap();
    }


    /**
     * Purchase-term links are only needed while rendering the storefront
     * footer, not while booting API, console or rejected HTTP requests.
     */
    private function purchaseTerms()
    {
        if (! $this->tableAvailableForView('pages')) {
            return collect();
        }

        return Cache::remember('storefront.purchase-terms.v1', now()->addMinutes(15), function () {
            return Page::query()
                ->where('subgroup', 'Uvjeti kupnje')
                ->select(['id', 'title', 'slug'])
                ->get();
        });
    }


    /**
     * Resolve and cache the navigation flag only for views which display it.
     */
    private function catalogActionsAvailable(): bool
    {
        if (! $this->tableAvailableForView('products')) {
            return false;
        }

        return (bool) Cache::remember(
            'catalog.navigation.has-actions.v2',
            now()->addMinutes(5),
            fn () => Product::query()->active()->hasStock()->onSale()->exists()
        );
    }


    /**
     * Production schema is managed by deployments. Runtime schema probes add
     * an information_schema query to every rendered view, so retain them only
     * for isolated test databases where individual tables may be absent.
     */
    private function tableAvailableForView(string $table): bool
    {
        return ! $this->app->environment('testing') || Schema::hasTable($table);
    }
}
