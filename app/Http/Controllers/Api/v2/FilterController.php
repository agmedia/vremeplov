<?php

namespace App\Http\Controllers\Api\v2;

use App\Helpers\Helper;
use App\Models\Back\Settings\Settings;
use App\Models\Front\Catalog\Product;
use App\Models\Back\Catalog\Product\ProductImage;
use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Publisher;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FilterController extends Controller
{

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function categories(Request $request)
    {
        if ( ! $request->input('params')) {
            return response()->json(['status' => 300, 'message' => 'Error!']);
        }

        $params = $request->input('params');

        // Uvijekprikaži sve kategorije
        $response = Helper::resolveCache('categories')->remember('nav', config('cache.life'), function () use ($params) {
            $groups = Category::getGroups();

            foreach ($groups as $key => $group) {
                $categories = Category::active()->topList($group->slug)->orderBy('title')->with('subcategories')->get()->toArray();
                $count      = Product::query()->where('group', $group->slug)->where('quantity', '>', 0)->count();

                $response[] = [
                    'id'    => $key,
                    'title' => $group->title,
                    'icon'  => '',
                    'count' => $count,//$category['products_count'],
                    'url'   => route('catalog.route', ['group' => $group->slug]),
                    'subs'  => $this->resolveCategoryArray($categories, 'categories')
                ];
            }

            return $response;
        });
        //}

        // Ako su posebni ID artikala.
        if (isset($params['ids']) && $params['ids'] != '[]') {
            $_ids = collect(explode(',', substr($params['ids'], 1, -1)))->unique();

            $categories = Category::active()->whereHas('products', function ($query) use ($_ids) {
                $query->active()->hasStock()->whereIn('id', $_ids);
            })->sortByName()->withCount('products')->get()->toArray();

            $response = $this->resolveCategoryArray($categories, 'categories');
        }

        $etag = sha1(json_encode($response));
        return response()
            ->json($response)
            ->setEtag($etag)
            ->setPublic()
            ->setMaxAge(config('cache.one_day'))        // 1 day
            ->header('Cache-Control', 'public, max-age=' . config('cache.one_day'));
    }


    /**
     * @param             $categories
     * @param string      $type
     * @param null        $target
     * @param string|null $parent_slug
     *
     * @return array
     */
    private function resolveCategoryArray($categories, string $type, $target = null, string $parent_slug = null): array
    {
        $response = [];

        foreach ($categories as $category) {
            $url  = $this->resolveCategoryUrl($category, $type, $target, $parent_slug);
            $subs = null;

            if (isset($category['subcategories']) && ! empty($category['subcategories'])) {
                foreach ($category['subcategories'] as $subcategory) {
                    $sub_url = $this->resolveCategoryUrl($subcategory, $type, $target, $category['slug']);

                    $subs[] = [
                        'id'    => $subcategory['id'],
                        'title' => $subcategory['title'],
                        'count' => 0,//Category::find($subcategory['id'])->products()->count(),
                        'url'   => $sub_url
                    ];
                }
            }

            $response[] = [
                'id'    => $category['id'],
                'title' => $category['title'],
                'icon'  => $category['image'],
                'count' => 0,//$category['products_count'],
                'url'   => $url,
                'subs'  => $subs
            ];


        }

        return $response;
    }


    /**
     * @param             $category
     * @param string      $type
     * @param             $target
     * @param string|null $parent_slug
     *
     * @return string
     */
    private function resolveCategoryUrl($category, string $type, $target, string $parent_slug = null): string
    {
        if ($type == 'author') {
            return route('catalog.route.author', [
                'author' => $target,
                'cat'    => $parent_slug ?: $category['slug'],
                'subcat' => $parent_slug ? $category['slug'] : null
            ]);

        } elseif ($type == 'publisher') {
            return route('catalog.route.publisher', [
                'publisher' => $target,
                'cat'       => $parent_slug ?: $category['slug'],
                'subcat'    => $parent_slug ? $category['slug'] : null
            ]);

        } else {
            return route('catalog.route', [
                'group'  => Str::slug($category['group']),
                'cat'    => $parent_slug ?: $category['slug'],
                'subcat' => $parent_slug ? $category['slug'] : null
            ]);
        }
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function products(Request $request)
    {
        if ( ! $request->has('params')) {
            return response()->json(['status' => 300, 'message' => 'Error!']);
        }

        $params = $request->input('params');

        if (isset($params['autor']) && $params['autor']) {
            if (strpos($params['autor'], '+') !== false) {
                $arr = explode('+', $params['autor']);

                foreach ($arr as $item) {
                    $_author         = Author::where('slug', $item)->first();
                    $this->authors[] = $_author;
                }

            } else {
                $_author         = Author::where('slug', $params['autor'])->first();
                $this->authors[] = $_author;
            }
        }

        if (isset($params['nakladnik']) && $params['nakladnik']) {
            if (strpos($params['nakladnik'], '+') !== false) {
                $arr = explode('+', $params['nakladnik']);

                foreach ($arr as $item) {
                    $_publisher         = Publisher::where('slug', $item)->first();
                    $this->publishers[] = $_publisher;
                }

            } else {
                $_publisher         = Publisher::where('slug', $params['nakladnik'])->first();
                $this->publishers[] = $_publisher;
            }
        }

        $request_data = [];

        if (isset($params['ids']) && $params['ids'] != '') {
            $request_data['ids'] = $params['ids'];
        }

        if (isset($params['group']) && $params['group']) {
            $request_data['group'] = $params['group'];
        }

        if (isset($params['cat']) && $params['cat']) {
            $request_data['cat'] = $params['cat'];
        }

        if (isset($params['subcat']) && $params['subcat']) {
            $request_data['subcat'] = $params['subcat'];
        }

        if (isset($params['autor']) && $params['autor']) {
            $request_data['autor'] = $this->authors;
        }

        if (isset($params['nakladnik']) && $params['nakladnik']) {
            $request_data['nakladnik'] = $this->publishers;
        }

        if (isset($params['start']) && $params['start']) {
            $request_data['start'] = $params['start'];
        }

        if (isset($params['end']) && $params['end']) {
            $request_data['end'] = $params['end'];
        }

        if (isset($params['sort']) && $params['sort']) {
            $request_data['sort'] = $params['sort'];
        }

        $request_data['page'] = $request->input('page');

        $request = new Request($request_data);

        // Build the query once
        /*$query = (new Product())->filter($request)
                                ->select(['id','name','slug','image','price','special','quantity','author_id','publisher_id','updated_at'])
                                ->with(['author:id,title,slug']); // keep this lean

        $page = (int) ($request->input('page', 1));
        $key  = 'filter.products:' . sha1(json_encode($request_data)) . ':p:' . $page;

        // Short TTL caches (shields bursts) – still fresh because we also add ETag
        $ttl  = 60; // seconds

        $products = \Cache::remember($key, $ttl, function () use ($query) {
            return $query->paginate(config('settings.pagination.front'));
        });

        // ETag tied to filter + last change time of the page slice
        $lastUpdated = optional(collect($products->items())->max('updated_at'))->timestamp ?? 0;
        $etag = sha1($key . ':' . $lastUpdated);

        // Honor If-None-Match
        $response = response()->json($products);
        if (request()->headers->get('If-None-Match') === $etag) {
            return $response->setEtag($etag)->setNotModified();
        }

        return $response
            ->setEtag($etag)
            ->setPublic()
            ->setMaxAge($ttl)
            ->header('Cache-Control', 'public, max-age=' . $ttl . ', stale-while-revalidate=30');*/

        $products = (new Product())->filter($request)
                                   ->with('author')
                                   ->paginate(config('settings.pagination.front'));


        return response()->json($products);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function authors(Request $request)
    {
        if ($request->has('params')) {
            $params = json_decode($request->input('params'), true);

            $response = (new Author())->filter($params)
                                      ->get()
                                      ->toArray();

        } else {
            $response = Helper::resolveCache('authors')->remember('featured', config('cache.life'), function () {
                return Author::query()->active()
                             ->featured()
                             ->basicData()
                             ->withCount('products')
                             ->get()
                             ->toArray();
            });
        }

        $etag = sha1(json_encode($response));
        return response()
            ->json($response)
            ->setEtag($etag)
            ->setPublic()
            ->setMaxAge(config('cache.one_day'))        // 1 day
            ->header('Cache-Control', 'public, max-age=' . config('cache.one_day'));
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function publishers(Request $request)
    {
        if ($request->has('params')) {
            $params = json_decode($request->input('params'), true);

            $response = (new Publisher())->filter($params)
                                         ->basicData()
                                         ->withCount('products')
                                         ->get()
                                         ->toArray();

        } else {
            $response = Helper::resolveCache('publishers')->remember('featured', config('cache.life'), function () {
                return Publisher::active()
                                ->featured()
                                ->basicData()
                                ->withCount('products')
                                ->get()
                                ->toArray();
            });
        }

        $etag = sha1(json_encode($response));
        return response()
            ->json($response)
            ->setEtag($etag)
            ->setPublic()
            ->setMaxAge(config('cache.one_day'))        // 1 day
            ->header('Cache-Control', 'public, max-age=' . config('cache.one_day'));
    }

}
