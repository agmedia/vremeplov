<?php

namespace App\Http\Controllers\Api\v2;

use App\Helpers\Helper;
use App\Models\Back\Settings\Settings;
use App\Models\Front\Catalog\Product;
use App\Models\Back\Catalog\Product\ProductImage;
use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Publisher;
use App\Support\CatalogFilterValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        if ( ! $request->has('params')) {
            return response()->json(['status' => 300, 'message' => 'Error!']);
        }

        $params = $this->requestParams($request);
        $response = [];
        $parentId = $this->categoryId($params['cat'] ?? null);
        $selectedSubcategoryId = $this->categoryId($params['subcat'] ?? null);
        $group = trim((string) ($params['group'] ?? ''));

        // Na korijenu grupe prikazujemo samo njezine kategorije, a unutar
        // kategorije samo njezine izravne podkategorije.
        if ($parentId) {
            $parent = Category::query()
                ->active()
                ->where('parent_id', 0)
                ->when($group !== '', fn (Builder $query) => $query->where('group', $group))
                ->find($parentId);

            if ($parent) {
                $response = $parent->subcategories()
                    ->active()
                    ->withCount('products')
                    ->get()
                    ->map(function (Category $category) use ($parent, $selectedSubcategoryId) {
                        return [
                            'id' => $category->id,
                            'title' => $category->title,
                            'count' => (int) $category->products_count,
                            'url' => $parent->url($category),
                            'active' => $selectedSubcategoryId === (int) $category->id,
                        ];
                    })
                    ->values()
                    ->all();
            }
        } elseif (($params['catalog_root'] ?? null) === 'all') {
            $counts = Product::query()
                ->active()
                ->hasStock()
                ->select('group', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('group')
                ->pluck('aggregate', 'group');

            $response = Category::getGroups()
                ->map(function ($catalogGroup) use ($counts) {
                    $count = (int) ($counts->get($catalogGroup->slug) ?? $counts->get($catalogGroup->title) ?? 0);

                    return [
                        'id' => 'group-' . $catalogGroup->slug,
                        'title' => $catalogGroup->title,
                        'count' => $count,
                        'url' => route('catalog.route', ['group' => $catalogGroup->slug]),
                        'active' => false,
                    ];
                })
                ->filter(fn ($catalogGroup) => $catalogGroup['count'] > 0)
                ->sortBy(fn ($catalogGroup) => Str::lower($catalogGroup['title']))
                ->values()
                ->all();
        } elseif ($group !== '') {
            $response = Category::query()
                ->active()
                ->where('parent_id', 0)
                ->where('group', $group)
                ->withCount('products')
                ->orderBy('title')
                ->get()
                ->map(function (Category $category) {
                    return [
                        'id' => $category->id,
                        'title' => $category->title,
                        'count' => (int) $category->products_count,
                        'url' => $category->url(),
                        'active' => false,
                    ];
                })
                ->values()
                ->all();
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
     * Return available product characteristics for the active catalog context.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function characteristics(Request $request)
    {
        $params = $this->requestParams($request);
        $response = [];

        foreach ([
            'letter' => ['key' => 'pismo', 'title' => 'Pismo'],
            'condition' => ['key' => 'stanje', 'title' => 'Stanje'],
            'binding' => ['key' => 'uvez', 'title' => 'Uvez'],
            'origin' => ['key' => 'jezik', 'title' => 'Jezik'],
        ] as $column => $definition) {
            $items = $this->productContextQuery($params, $definition['key'])
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->select($column . ' as value', DB::raw('COUNT(*) as count'))
                ->groupBy($column)
                ->get()
                ->flatMap(function ($item) use ($column) {
                    $rawValue = trim((string) $item->value);

                    return collect(CatalogFilterValue::facetValues($column, $rawValue))
                        ->map(function (string $label) use ($item) {
                            return [
                                'value' => CatalogFilterValue::key($label),
                                'label' => $label,
                                'count' => (int) $item->count,
                            ];
                        });
                })
                ->filter(function ($item) {
                    return $item['value'] !== '' && $item['label'] !== '';
                })
                ->groupBy('value')
                ->map(function ($variants, $value) {
                    $preferred = $variants->sortByDesc('count')->first();

                    return [
                        'value' => $value,
                        'label' => $preferred['label'],
                        'count' => $variants->sum('count'),
                    ];
                })
                ->sortByDesc('count')
                ->values();

            if ($items->isNotEmpty()) {
                $response[] = [
                    'key' => $definition['key'],
                    'title' => $definition['title'],
                    'items' => $items,
                ];
            }
        }

        $etag = sha1(json_encode($response));

        return response()
            ->json($response)
            ->setEtag($etag)
            ->setPublic()
            ->setMaxAge(config('cache.one_day'))
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

        $params = $this->requestParams($request);
        $authors = [];
        $publishers = [];

        if ( ! empty($params['autor'])) {
            $authors = Author::query()
                ->whereIn('slug', $this->entitySlugs($params['autor']))
                ->get()
                ->all();
        }

        if ( ! empty($params['nakladnik'])) {
            $publishers = Publisher::query()
                ->whereIn('slug', $this->entitySlugs($params['nakladnik']))
                ->get()
                ->all();
        }

        $request_data = [];

        if (isset($params['ids']) && $params['ids'] != '') {
            $request_data['ids'] = $params['ids'];
        }

        if (isset($params['group']) && $params['group']) {
            $request_data['group'] = $params['group'];
        }

        if (isset($params['cat']) && $params['cat']) {
            $request_data['cat'] = $this->categoryId($params['cat']);
        }

        if (isset($params['subcat']) && $params['subcat']) {
            $request_data['subcat'] = $this->categoryId($params['subcat']);
        }

        if (isset($params['autor']) && $params['autor']) {
            $request_data['autor'] = $authors;
        }

        if (isset($params['nakladnik']) && $params['nakladnik']) {
            $request_data['nakladnik'] = $publishers;
        }

        if (isset($params['start']) && $params['start']) {
            $request_data['start'] = $params['start'];
        }

        if (isset($params['end']) && $params['end']) {
            $request_data['end'] = $params['end'];
        }

        foreach (['pismo', 'stanje', 'uvez', 'jezik'] as $attribute) {
            if ( ! empty($params[$attribute])) {
                $request_data[$attribute] = $this->filterValues($params[$attribute]);
            }
        }

        if (isset($params['sort']) && $params['sort']) {
            $request_data['sort'] = $params['sort'];
        }

        $request_data['page'] = $request->input('page');

        $request = new Request($request_data);

        // Build the query once
        /*$query = (new Product())->filter($request)
                                ->select(['id','name','slug','url','image','price','special','quantity','author_id','publisher_id','updated_at'])
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
            $params = array_merge([
                'ids' => '',
                'group' => '',
                'cat' => '',
                'subcat' => '',
                'author' => '',
                'publisher' => '',
                'search_author' => '',
                'search_publisher' => '',
            ], $this->requestParams($request));

            $response = $this->authorFacet($params);

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
            $params = array_merge([
                'ids' => '',
                'group' => '',
                'cat' => '',
                'subcat' => '',
                'author' => '',
                'publisher' => '',
                'search_author' => '',
                'search_publisher' => '',
            ], $this->requestParams($request));

            $response = $this->publisherFacet($params);

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


    /**
     * @param Request $request
     *
     * @return array
     */
    private function requestParams(Request $request): array
    {
        $params = $request->input('params', []);

        if (is_array($params)) {
            return $params;
        }

        if (is_string($params)) {
            $decoded = json_decode($params, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }


    /**
     * @param mixed $value
     *
     * @return int|null
     */
    private function categoryId($value): ?int
    {
        if (is_array($value)) {
            $value = $value['id'] ?? null;
        } elseif (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? ($decoded['id'] ?? null) : null;
        }

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }


    /**
     * @param mixed $value
     *
     * @return array
     */
    private function filterValues($value): array
    {
        $values = is_array($value) ? $value : preg_split('/[+|]/', (string) $value);

        return collect($values)
            ->map(function ($item) {
                return trim((string) $item);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }


    /**
     * Resolve one or more grouped entity values back to their real slugs.
     * Commas are reserved for aliases inside one visually de-duplicated option.
     *
     * @param mixed $value
     *
     * @return array
     */
    private function entitySlugs($value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->flatMap(function ($item) {
                return preg_split('/[+|,]/', (string) $item) ?: [];
            })
            ->map(fn ($slug) => trim((string) $slug))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }


    /**
     * Authors available in the current product context, with obvious duplicate
     * name variants represented by one checkbox.
     */
    private function authorFacet(array $params): array
    {
        $counts = $this->productContextQuery($params, 'autor')
            ->whereNotNull('author_id')
            ->select('author_id', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('author_id')
            ->pluck('aggregate', 'author_id');

        if ($counts->isEmpty()) {
            return [];
        }

        $selectedSlugs = $this->entitySlugs($params['autor'] ?? ($params['author'] ?? []));
        $search = trim((string) ($params['search_author'] ?? ''));
        $query = Author::query()
            ->active()
            ->whereIn('id', $counts->keys());

        if ($search !== '') {
            $query->where('title', 'like', '%' . $search . '%');
        } else {
            $query->where(function (Builder $query) use ($selectedSlugs) {
                $query->where('featured', 1);
                if ($selectedSlugs) {
                    $query->orWhereIn('slug', $selectedSlugs);
                }
            });
        }

        return $this->groupEntityFacet(
            $query->limit(200)->get(['id', 'title', 'slug', 'url']),
            $counts,
            true
        );
    }


    /**
     * Publishers available in the current product context.
     */
    private function publisherFacet(array $params): array
    {
        $counts = $this->productContextQuery($params, 'nakladnik')
            ->whereNotNull('publisher_id')
            ->select('publisher_id', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('publisher_id')
            ->pluck('aggregate', 'publisher_id');

        if ($counts->isEmpty()) {
            return [];
        }

        $selectedSlugs = $this->entitySlugs($params['nakladnik'] ?? ($params['publisher'] ?? []));
        $search = trim((string) ($params['search_publisher'] ?? ''));
        $query = Publisher::query()
            ->active()
            ->whereIn('id', $counts->keys());

        if ($search !== '') {
            $query->where('title', 'like', '%' . $search . '%');
        } else {
            $query->where(function (Builder $query) use ($selectedSlugs) {
                $query->where('featured', 1);
                if ($selectedSlugs) {
                    $query->orWhereIn('slug', $selectedSlugs);
                }
            });
        }

        return $this->groupEntityFacet(
            $query->limit(200)->get(['id', 'title', 'slug', 'url']),
            $counts,
            false
        );
    }


    /**
     * @param \Illuminate\Support\Collection $entities
     * @param \Illuminate\Support\Collection $counts
     */
    private function groupEntityFacet($entities, $counts, bool $people): array
    {
        $groups = $people
            ? CatalogFilterValue::groupPeople($entities)
            : $entities->groupBy(fn ($entity) => CatalogFilterValue::key($entity->title));

        return collect($groups)
            ->map(function ($variants) use ($counts) {
                $preferred = $variants
                    ->sortByDesc(fn ($entity) => CatalogFilterValue::personLabelScore($entity->title))
                    ->first();
                $slugs = $variants->pluck('slug')->filter()->unique()->sort()->values();

                return [
                    'id' => $preferred->id,
                    'title' => CatalogFilterValue::display($preferred->title),
                    'slug' => $slugs->implode(','),
                    'slugs' => $slugs->all(),
                    'url' => $preferred->url,
                    'products_count' => (int) $variants->sum(fn ($entity) => (int) ($counts[$entity->id] ?? 0)),
                ];
            })
            ->filter(fn ($entity) => $entity['slug'] !== '' && $entity['products_count'] > 0)
            ->sortBy(fn ($entity) => Str::lower($entity['title']))
            ->values()
            ->all();
    }


    /**
     * @param array $params
     *
     * @return Builder
     */
    private function productContextQuery(array $params, ?string $exceptFacet = null): Builder
    {
        $query = Product::query()->active()->hasStock();

        if ( ! empty($params['ids'])) {
            $ids = is_array($params['ids'])
                ? $params['ids']
                : explode(',', trim((string) $params['ids'], '[]'));

            $query->whereIn('id', collect($ids)->filter(function ($id) {
                return is_numeric($id);
            })->map(function ($id) {
                return (int) $id;
            })->unique());
        }

        if ( ! empty($params['group'])) {
            $query->where('group', $params['group']);
        }

        $subcategoryId = $this->categoryId($params['subcat'] ?? null);
        $categoryId = $this->categoryId($params['cat'] ?? null);
        foreach (array_filter([$categoryId, $subcategoryId]) as $contextCategoryId) {
            $query->whereHas('categories', function ($categoryQuery) use ($contextCategoryId) {
                $categoryQuery->where('category_id', $contextCategoryId);
            });
        }

        $authorSlugs = $this->entitySlugs($params['autor'] ?? ($params['author'] ?? []));
        if ($exceptFacet !== 'autor' && $authorSlugs) {
            $query->whereIn('author_id', Author::query()->whereIn('slug', $authorSlugs)->pluck('id'));
        }

        $publisherSlugs = $this->entitySlugs($params['nakladnik'] ?? ($params['publisher'] ?? []));
        if ($exceptFacet !== 'nakladnik' && $publisherSlugs) {
            $query->whereIn('publisher_id', Publisher::query()->whereIn('slug', $publisherSlugs)->pluck('id'));
        }

        if ( ! empty($params['start'])) {
            $query->where(function ($yearQuery) use ($params) {
                $yearQuery->where('year', '>=', $params['start'])->orWhereNull('year');
            });
        }

        if ( ! empty($params['end'])) {
            $query->where(function ($yearQuery) use ($params) {
                $yearQuery->where('year', '<=', $params['end'])->orWhereNull('year');
            });
        }

        foreach ([
            'pismo' => 'letter',
            'stanje' => 'condition',
            'uvez' => 'binding',
            'jezik' => 'origin',
        ] as $parameter => $column) {
            if ($parameter === $exceptFacet || empty($params[$parameter])) {
                continue;
            }

            $values = collect($this->filterValues($params[$parameter]))
                ->map(fn ($value) => CatalogFilterValue::facetKey($column, $value))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($values) {
                $query->whereFacetValues($column, $values);
            }
        }

        return $query;
    }


}
