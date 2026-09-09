<?php

namespace App\Http\Controllers\Front;

use App\Helpers\Breadcrumb;
use App\Helpers\Helper;
use App\Helpers\RouteResolver;
use App\Http\Controllers\Controller;
use App\Imports\ProductImport;
use App\Models\Back\Settings\Settings;
use App\Models\Front\Blog;
use App\Models\Front\Page;
use App\Models\Front\Faq;
use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\Publisher;
use App\Models\Seo;
use App\Models\TagManager;
use App\Support\CatalogFilterValue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    public function resolve(Request $request, $group, $cat = null, $subcat = null, ?Product $prod = null)
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

        if (is_string($cat)) {
            Log::info($group);
            Log::info($cat);
        }

        // Ako je artikl prvotno postavljen ili
        // ako je postavljen umjest kategorije ili podkategorije
        if ($prod) {
            if ( ! $prod->status) {
                abort(404);
            }

            $prod->timestamps = false;
            $prod->increment('viewed');
            $prod->timestamps = true;

            $seo = Seo::getProductData($prod);
            $gdl = TagManager::getGoogleProductDataLayer($prod);

            $recent = collect(session('recent_products', []));
            $recent = $recent->prepend($prod->id)->unique()->values()->take(50);
            session(['recent_products' => $recent->all()]);

            $recentIds = $recent
                ->filter(fn ($id) => (int) $id !== (int) $prod->id)
                ->take(15)
                ->values()
                ->all();

            $recentProducts = collect();

            if (! empty($recentIds)) {
                $recentProducts = Product::query()
                    ->whereIn('id', $recentIds)
                    ->where('status', 1)
                    ->get()
                    ->sortBy(fn ($product) => array_search($product->id, $recentIds))
                    ->values();
            }

            $bc = new Breadcrumb();
            $crumbs = $bc->product($group, $cat, $subcat, $prod)->resolve();
            $bookscheme = $bc->productBookSchema($prod);

            $reviews = $prod->reviews()->get();
            $related = Helper::getRelated($group, $cat, $subcat);
            $shipping_methods = Settings::getList('shipping', 'list.%', true);
            $payment_methods = Settings::getList('payment', 'list.%', true);

            return view('front.catalog.product.index', compact(
                'prod',
                'group',
                'cat',
                'subcat',
                'related',
                'seo',
                'crumbs',
                'bookscheme',
                'gdl',
                'reviews',
                'shipping_methods',
                'payment_methods',
                'recentProducts'
            ));
        }

        $meta = $resolver->setMeta();
        $crumbs = (new Breadcrumb())->category($group, $cat, $subcat)->resolve();
        $products = $this->catalogProducts($request, $group, $cat, $subcat);

        return view('front.catalog.category.index', compact('group', 'cat', 'subcat', 'prod', 'meta', 'crumbs', 'products'));
    }


    /**
     *
     *
     * @param Author $author
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function author(Request $request, ?Author $author = null, ?Category $cat = null, ?Category $subcat = null)
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
        $products = $this->catalogProducts($request, null, $cat, $subcat, null, $author);

        return view('front.catalog.category.index', compact('author', 'cat', 'subcat', 'meta', 'crumbs', 'products'));
    }


    /**
     *
     *
     * @param Publisher $publisher
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function publisher(Request $request, ?Publisher $publisher = null, ?Category $cat = null, ?Category $subcat = null)
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
        $products = $this->catalogProducts($request, null, $cat, $subcat, null, null, $publisher);

        return view('front.catalog.category.index', compact('publisher', 'cat', 'subcat', 'meta', 'crumbs', 'products'));
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

            $searchTerm = trim((string) $request->input(config('settings.search_keyword')));
            $ids = collect(json_decode((string) Helper::search($searchTerm), true) ?: []);
            $group = null;
            $cat = null;
            $subcat = null;
            $crumbs = null;
            $meta = [
                'title' => 'Pretraga: ' . $searchTerm,
                'description' => 'Rezultati pretrage artikala za pojam „' . $searchTerm . '”.',
                'canonical' => route('pretrazi', [config('settings.search_keyword') => $searchTerm]),
                'tags' => [['name' => 'robots', 'content' => 'noindex,follow']],
            ];
            $products = $this->catalogProducts($request, null, null, null, $ids);

            return view('front.catalog.category.index', compact('ids', 'group', 'cat', 'subcat', 'crumbs', 'meta', 'products'));
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
     * Lightweight autosuggest for header search.
     */
    public function suggest(Request $request)
    {
        $query = trim((string) $request->get('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['authors' => [], 'products' => []]);
        }

        $authorBasePath = trim((string) config('settings.author_path'), '/');
        $carlJungUrl = DB::table('authors')
            ->where('status', 1)
            ->where('slug', 'carl-gustav-jung')
            ->value('url') ?: ($authorBasePath . '/carl-gustav-jung');

        $authorRows = DB::table('authors')
            ->where('status', 1)
            ->where('title', 'like', '%' . $query . '%')
            ->orderByRaw(
                'CASE
                    WHEN TRIM(title) = ? THEN 0
                    WHEN title LIKE ? OR title LIKE ? OR title LIKE ? THEN 1
                    ELSE 2
                END',
                [$query, $query . ' %', '% ' . $query . ' %', '% ' . $query]
            )
            ->orderBy('title')
            ->limit(30)
            ->get(['title', 'slug', 'url']);

        $authors = collect(CatalogFilterValue::groupPeople($authorRows))
            ->map(function ($variants) use ($authorBasePath, $carlJungUrl) {
                $preferred = $variants
                    ->sortByDesc(fn ($author) => CatalogFilterValue::personLabelScore($author->title))
                    ->first();
                $title = CatalogFilterValue::display($preferred->title);
                $isCarlJung = $variants->contains(function ($author) {
                    $key = str_replace(' ', '', CatalogFilterValue::key($author->title));

                    return in_array($key, [
                        'cgjung',
                        'jung',
                        'gjungc',
                        'gustavjungcarl',
                        'gustavjungkarl',
                        'carlgustavjung',
                    ], true);
                });
                $url = $preferred->url ?: ($authorBasePath . '/' . $preferred->slug);

                return [
                    'title' => $isCarlJung ? 'Carl Gustav Jung' : $title,
                    'url' => $isCarlJung ? $carlJungUrl : $url,
                    'key' => $isCarlJung ? 'carl-gustav-jung' : CatalogFilterValue::entityKey($title),
                ];
            })
            ->unique(function ($author) {
                return $author['key'];
            })
            ->take(4)
            ->map(function ($author) {
                unset($author['key']);

                return $author;
            })
            ->values();

        $products = DB::table('products as p')
            ->leftJoin('authors as a', 'a.id', '=', 'p.author_id')
            ->where('p.status', 1)
            ->where(function ($q) use ($query) {
                $q->where('p.name', 'like', '%' . $query . '%')
                    ->orWhere('p.sku', 'like', '%' . $query . '%')
                    ->orWhere('a.title', 'like', '%' . $query . '%');
            })
            ->orderByRaw('CASE WHEN p.name LIKE ? THEN 0 ELSE 1 END', [$query . '%'])
            ->orderByRaw('CASE WHEN p.quantity > 0 THEN 0 ELSE 1 END')
            ->orderByDesc('p.viewed')
            ->limit(40)
            ->get([
                'p.name',
                'p.url',
                'p.image',
                'p.quantity',
                'p.price',
                'p.special',
                'p.special_from',
                'p.special_to',
                'a.title as author'
            ])
            ->map(function ($product) {
                $now = now();
                $imagesDomain = rtrim((string) config('settings.images_domain'), '/') . '/';
                $specialFrom = $product->special_from;
                $specialTo = $product->special_to;
                $hasSpecialFrom = $specialFrom && $specialFrom !== '0000-00-00 00:00:00';
                $hasSpecialTo = $specialTo && $specialTo !== '0000-00-00 00:00:00';

                $hasSpecial = !is_null($product->special);
                $specialFromOk = !$hasSpecialFrom || Carbon::parse($specialFrom) <= $now;
                $specialToOk = !$hasSpecialTo || Carbon::parse($specialTo) >= $now;

                $effectivePrice = ($hasSpecial && $specialFromOk && $specialToOk)
                    ? (float) $product->special
                    : (float) $product->price;

                $rawImage = ltrim((string) ($product->image ?? ''), '/');
                $baseImage = $rawImage !== ''
                    ? str_replace('.jpg', '.webp', $rawImage)
                    : 'media/img/knjiga-detalj.jpg';
                $image = $imagesDomain . $baseImage;
                $thumb = str_ends_with($baseImage, '.webp')
                    ? $imagesDomain . str_replace('.webp', '-thumb.webp', $baseImage)
                    : $image;

                return [
                    'name' => $product->name,
                    'url' => $product->url,
                    'author' => $product->author,
                    'quantity' => (int) $product->quantity,
                    'price' => round($effectivePrice, 2),
                    'image' => $thumb,
                ];
            })
            ->unique(function ($product) {
                return mb_strtolower(trim((string) $product['name'])) . '|' . mb_strtolower(trim((string) $product['author']));
            })
            ->take(6)
            ->values();

        return response()->json([
            'authors' => $authors,
            'products' => $products
        ]);
    }


    public function tag(Request $request)
    {
        $key = config('settings.search_keyword', 'pojam');
        $query = $request->input($key);

        if ($query === null) {
            return redirect()->back()->with(['error' => 'Nedostaje parametar pretrage.']);
        }

        if ($query === '') {
            return redirect()->back()->with(['error' => 'Oops..! Zaboravili ste upisati pojam za pretraživanje..!']);
        }

        $products = Helper::search($query, true);
        $ids = collect($products->get('products', collect()));

        $group = null;
        $cat = null;
        $subcat = null;
        $crumbs = null;
        $meta = [
            'title' => 'Rezultati za: ' . $query,
            'description' => 'Artikli povezani s pojmom „' . $query . '” u ponudi Antikvarijata Vremeplov.',
            'canonical' => route('tag', [$key => $query]),
            'tags' => [['name' => 'robots', 'content' => 'noindex,follow']],
        ];
        $products = $this->catalogProducts($request, null, null, null, $ids);

        return view('front.catalog.category.index', compact('group', 'cat', 'subcat', 'ids', 'crumbs', 'meta', 'products'));
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function actions(Request $request, ?Category $cat = null, ?Category $subcat = null)
    {
        $group = null;
        $ids = Product::query()->active()->hasStock()->onSale()->pluck('id');
        $crumbs = null;
        $meta = [
            'title' => 'Akcijska ponuda',
            'description' => 'Sniženi artikli i posebne ponude Antikvarijata Vremeplov.',
            'canonical' => route('catalog.route.actions'),
            'tags' => [],
        ];
        $products = $this->catalogProducts($request, null, $cat, $subcat, $ids);

        return view('front.catalog.category.index', compact('group', 'cat', 'subcat', 'ids', 'crumbs', 'meta', 'products'));
    }


    /**
     * Render the initial catalog page on the server so products and pagination
     * remain discoverable when JavaScript is unavailable.
     */
    private function catalogProducts(
        Request $request,
        ?string $group = null,
        ?Category $cat = null,
        ?Category $subcat = null,
        $ids = null,
        ?Author $author = null,
        ?Publisher $publisher = null
    ) {
        $data = $request->only(['start', 'end', 'sort', 'pismo', 'stanje', 'uvez', 'jezik']);

        if ($group) {
            $data['group'] = $group;
        }

        if ($cat) {
            $data['cat'] = $cat->id;
        }

        if ($subcat) {
            $data['subcat'] = $subcat->id;
        }

        if ($author) {
            $data['autor'] = [$author];
        } elseif ($request->filled('autor')) {
            $data['autor'] = Author::query()
                ->whereIn('slug', preg_split('/[+,]/', (string) $request->input('autor')) ?: [])
                ->get();
        }

        if ($publisher) {
            $data['nakladnik'] = [$publisher];
        } elseif ($request->filled('nakladnik')) {
            $data['nakladnik'] = Publisher::query()
                ->whereIn('slug', preg_split('/[+,]/', (string) $request->input('nakladnik')) ?: [])
                ->get();
        }

        if ($ids !== null) {
            $ids = collect(is_string($ids) ? (json_decode($ids, true) ?: []) : $ids)
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $data['ids'] = '[' . ($ids->isEmpty() ? '0' : $ids->implode(',')) . ']';
        }

        $filterRequest = new Request($data);

        return (new Product())->filter($filterRequest)
            ->with(['author', 'action'])
            ->paginate(config('settings.pagination.front'))
            ->appends($request->query());
    }

}
