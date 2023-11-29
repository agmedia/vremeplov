<?php

namespace App\Providers;

use App\Helpers\Helper;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Page;
use App\Models\User;
use App\Models\Front\Catalog\Product;
use Illuminate\Pagination\Paginator;
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

        $uvjeti_kupnje = Page::where('subgroup', 'Uvjeti kupnje')->get();
        View::share('uvjeti_kupnje', $uvjeti_kupnje);

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
