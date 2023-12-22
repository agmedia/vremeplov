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

        $response = [];
        $params   = $request->input('params');

        // Ako je normal kategorija
        /*if ($params['group']) {
            $response = Helper::resolveCache('categories')->remember($params['group'], config('cache.life'), function () use ($params) {
                $categories = Category::active()->topList($params['group'])->sortByName()->with('subcategories')->get()->toArray();

                return $this->resolveCategoryArray($categories, 'categories');
            });
        }*/

        // Ako je autor
        /*if ( ! $params['group'] && $params['author']) {
            $author   = Author::where('slug', $params['author'])->first();
            $a_cats   = $author->categories();
            $response = $this->resolveCategoryArray($a_cats, 'author', $author);
        }*/

        // Ako je nakladnik
        /*if ( ! $params['group'] && $params['publisher']) {
            $publisher = Publisher::where('slug', $params['publisher'])->first();
            $a_cats    = $publisher->categories();
            $response  = $this->resolveCategoryArray($a_cats, 'publisher', $publisher);
        }*/

        //
        //if ( ! $params['group'] && ! $params['author'] && ! $params['publisher']) {
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
        if ($params['ids'] && $params['ids'] != '[]') {
            $_ids = collect(explode(',', substr($params['ids'], 1, -1)))->unique();

            $categories = Category::active()->whereHas('products', function ($query) use ($_ids) {
                $query->active()->hasStock()->whereIn('id', $_ids);
            })->sortByName()->withCount('products')->get()->toArray();

            $response = $this->resolveCategoryArray($categories, 'categories');
        }

        return response()->json($response);
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
            return response()->json(
                (new Author())->filter($request->input('params'))
                              ->get()
                              ->toArray()
            );
        }

        return response()->json(
            Helper::resolveCache('authors')->remember('featured', config('cache.life'), function () {
                return Author::query()->active()
                             ->featured()
                             ->basicData()
                             ->withCount('products')
                             ->get()
                             ->toArray();
            })
        );
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function publishers(Request $request)
    {
        if ($request->has('params')) {
            return response()->json(
                (new Publisher())->filter($request->input('params'))
                                 ->basicData()
                                 ->withCount('products')
                                 ->get()
                                 ->toArray()
            );
        }

        return response()->json(
            Helper::resolveCache('publishers')->remember('featured', config('cache.life'), function () {
                return Publisher::active()
                                ->featured()
                                ->basicData()
                                ->withCount('products')
                                ->get()
                                ->toArray();
            })
        );
    }

}
