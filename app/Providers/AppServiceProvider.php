<?php

namespace App\Providers;

use App\Helpers\Helper;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Page;
use App\Models\User;
use App\Models\Front\Catalog\Product;
use App\Models\Back\Marketing\Review;
use App\Models\Back\Marketing\Wishlist;
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
        //

        $uvjeti_kupnje = $this->app->environment('testing') && ! Schema::hasTable('pages')
            ? collect()
            : Page::where('subgroup', 'Uvjeti kupnje')->get();
        View::share('uvjeti_kupnje', $uvjeti_kupnje);

        $hasCatalogActions = Schema::hasTable('products')
            ? Cache::remember('catalog.navigation.has-actions.v2', now()->addMinutes(5), function () {
                return Product::query()->active()->hasStock()->onSale()->exists();
            })
            : false;
        View::share('hasCatalogActions', $hasCatalogActions);

        View::composer('back.layouts.partials.topbar', function ($view) {
            $wishlistReadyCount = Schema::hasTable('wishlist') && Schema::hasTable('products')
                ? Wishlist::query()->readyToSend()->count()
                : 0;
            $pendingCommentCount = Schema::hasTable('reviews')
                ? Review::query()->where('status', 0)->count()
                : 0;

            $view->with(compact('wishlistReadyCount', 'pendingCommentCount'));
        });

        View::composer('front.layouts.partials.header', function ($view) {
            $mobileNavigationGroups = collect();
            $mobileNavigationBookCategories = collect();

            if (Schema::hasTable('categories')) {
                $mobileNavigationGroups = Category::getGroups();
                $bookNavigationGroup = $mobileNavigationGroups->firstWhere('slug', 'knjige');
                $bookNavigationGroupSlug = $bookNavigationGroup->slug ?? 'knjige';
                $mobileNavigationBookCategories = Helper::resolveCache('categories')->remember(
                    'mobile-navigation.books.v3',
                    config('cache.life'),
                    function () use ($bookNavigationGroupSlug) {
                        return Category::query()
                            ->active()
                            ->topList($bookNavigationGroupSlug)
                            ->orderBy('title')
                            ->select('id', 'title', 'group', 'slug', 'sort_order')
                            ->with(['subcategories' => function ($query) {
                                $query->select('id', 'parent_id', 'title', 'group', 'slug', 'sort_order')
                                    ->orderBy('title');
                            }])
                            ->get();
                    }
                );
            }

            $view->with(compact('mobileNavigationGroups', 'mobileNavigationBookCategories'));
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
}
