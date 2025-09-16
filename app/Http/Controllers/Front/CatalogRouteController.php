<?php

namespace App\Http\Controllers\Front;

use App\Helpers\Breadcrumb;
use App\Helpers\Helper;
use App\Helpers\RouteResolver;
use App\Http\Controllers\Controller;
use App\Imports\ProductImport;
use App\Models\Front\Blog;
use App\Models\Front\Page;
use App\Models\Front\Faq;
use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\Publisher;
use App\Models\Seo;
use App\Models\TagManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CatalogRouteController extends Controller
{

    /**
     * Resolver for the Groups, categories and products routes.
     * Route::get('{group}/{cat?}/{subcat?}/{prod?}', 'Front\GCP_RouteController::resolve()')->name('gcp_route');
     *
     * @param               $group
     * @param Category||null $cat
     * @param Category|null $subcat
     * @param Product|null  $prod
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function resolve(Request $request, $group, $cat = null, $subcat = null, Product $prod = null)
    {
        $resolver = new RouteResolver($request, $group, $cat, $subcat, $prod);

        if ($resolver->isUnwantedRoute()) {
            return;
        }

        $resolver->isAllowedGroup()->setRoute();

        $group = $resolver->group;
        $cat = $resolver->category;
        $subcat = $resolver->subcategory;
        $prod = $resolver->product;

        Log::info($group);
        Log::info($cat);
        Log::info($subcat);
        Log::info($prod);

        // Ako je artikl prvotno postavljen ili
        // ako je postavljen umjest kategorije ili podkategorije
        if ($prod) {
            if ( ! $prod->status) {
                abort(404);
            }

            $seo = Seo::getProductData($prod);
            $gdl = TagManager::getGoogleProductDataLayer($prod);

            $bc = new Breadcrumb();
            $crumbs = $bc->product($group, $cat, $subcat, $prod)->resolve();
            $bookscheme = $bc->productBookSchema($prod);

            $reviews = $prod->reviews()->get();
            $related = Helper::getRelated($group, $cat, $subcat);

            return view('front.catalog.product.index', compact('prod', 'group', 'cat', 'subcat', 'related', 'seo', 'crumbs', 'bookscheme', 'gdl', 'reviews'));
        }

        $meta = $resolver->setMeta();
        $crumbs = (new Breadcrumb())->category($group, $cat, $subcat)->resolve();

        return view('front.catalog.category.index', compact('group', 'cat', 'subcat', 'prod', 'meta', 'crumbs'));
    }


    /**
     *
     *
     * @param Author $author
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function author(Request $request, Author $author = null, Category $cat = null, Category $subcat = null)
    {
        if ( ! $author) {
            $letters = Author::getLetters();
            $letter = Helper::resolveLetter($request);
            $authors = Author::getByLetter($letter);
            $meta_tags = Seo::getMetaTags($request, 'ap_filter');

            return view('front.catalog.authors.index', compact('authors', 'letters', 'letter', 'meta_tags'));
        }

        $meta = Seo::getAuthorData($author, $cat, $subcat);
        $crumbs = (new Breadcrumb())->author($author, $cat, $subcat)->resolve();

        return view('front.catalog.category.index', compact('author', 'cat', 'subcat', 'meta', 'crumbs'));
    }


    /**
     *
     *
     * @param Publisher $publisher
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function publisher(Request $request, Publisher $publisher = null, Category $cat = null, Category $subcat = null)
    {
        if ( ! $publisher) {
            $letters = Publisher::getLetters();
            $letter = Helper::resolveLetter($request);
            $publishers = Publisher::getByLetter($letter);
            $meta_tags = Seo::getMetaTags($request, 'ap_filter');

            return view('front.catalog.publishers.index', compact('publishers', 'letters', 'letter', 'meta_tags'));
        }

        $meta = Seo::getPublisherData($publisher, $cat, $subcat);
        $crumbs = (new Breadcrumb())->publisher($publisher, $cat, $subcat)->resolve();

        return view('front.catalog.category.index', compact('publisher', 'cat', 'subcat', 'meta', 'crumbs'));
    }


    /**
     *
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        if ($request->has(config('settings.search_keyword'))) {
            if ( ! $request->input(config('settings.search_keyword'))) {
                return redirect()->back()->with(['error' => 'Oops..! Zaboravili ste upisati pojam za pretraživanje..!']);
            }

            $ids = Helper::search(
                $request->input(config('settings.search_keyword'))
            );

            return view('front.catalog.category.index', compact('ids'));
        }

        if ($request->has(config('settings.search_keyword') . '_api')) {
            $search = Helper::search(
                $request->input(config('settings.search_keyword') . '_api')
            );

            return response()->json($search);
        }

        return response()->json(['error' => 'Greška kod pretrage..! Molimo pokušajte ponovo ili nas kotaktirajte! HVALA...']);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function actions(Request $request, Category $cat = null, $subcat = null)
    {
        $group = 'snizenja';
        $ids = Product::query()->whereNotNull('special')->pluck('id');

        return view('front.catalog.category.index', compact('group', 'cat', 'subcat', 'ids'));
    }

}
