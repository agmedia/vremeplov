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
    private const CHARACTERISTIC_FACETS = [
        'letter' => ['key' => 'pismo', 'title' => 'Pismo'],
        'condition' => ['key' => 'stanje', 'title' => 'Stanje'],
        'binding' => ['key' => 'uvez', 'title' => 'Uvez'],
        'origin' => ['key' => 'jezik', 'title' => 'Jezik'],
    ];

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
        $cacheKey = 'catalog.characteristic-facets:' . sha1(json_encode(
            $this->characteristicFacetCacheParams($params),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
        $response = Cache::remember($cacheKey, 60, function () use ($params) {
            return $this->characteristicFacets($params);
        });

        $etag = sha1(json_encode($response));

        return response()
            ->json($response)
            ->setEtag($etag)
            ->header('Cache-Control', 'private, no-store');
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
                                   ->cardData()
                                   ->paginate(config('settings.pagination.front'));

        $products->getCollection()->each(function (Product $product) {
            $category = $product->categories->firstWhere('parent_id', 0) ?: $product->categories->first();
            $product->setAttribute('card_category', $category ? [
                'title' => $category->title,
                'url' => $category->url(),
            ] : null);
        });


        return response()->json($products);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function authors(Request $request)
    {
        $dynamic = $request->has('params');

        if ($dynamic) {
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
            $cacheKey = $this->entityFacetCacheKey($params, 'author');
            $response = Cache::remember($cacheKey, 60, function () use ($params) {
                return $this->authorFacet($params);
            });

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

        $result = response()
            ->json($response)
            ->setEtag(sha1(json_encode($response)));

        return $dynamic
            ? $result->header('Cache-Control', 'private, no-store')
            : $result->setPublic()
                ->setMaxAge(config('cache.one_day'))
                ->header('Cache-Control', 'public, max-age=' . config('cache.one_day'));
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function publishers(Request $request)
    {
        $dynamic = $request->has('params');

        if ($dynamic) {
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
            $cacheKey = $this->entityFacetCacheKey($params, 'publisher');
            $response = Cache::remember($cacheKey, 60, function () use ($params) {
                return $this->publisherFacet($params);
            });

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

        $result = response()
            ->json($response)
            ->setEtag(sha1(json_encode($response)));

        return $dynamic
            ? $result->header('Cache-Control', 'private, no-store')
            : $result->setPublic()
                ->setMaxAge(config('cache.one_day'))
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
     * Build all four disjunctive characteristic facets from one grouped query.
     * Other facet selections are combined with AND, while multiple values
     * selected inside the facet being counted remain OR choices.
     */
    private function characteristicFacets(array $params): array
    {
        $columns = array_keys(self::CHARACTERISTIC_FACETS);
        $facetKeys = collect(self::CHARACTERISTIC_FACETS)->pluck('key')->all();
        $rows = $this->productContextQuery($params, $facetKeys)
            ->select($columns)
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy($columns)
            ->get();
        $selections = collect(self::CHARACTERISTIC_FACETS)
            ->mapWithKeys(function (array $definition, string $column) use ($params) {
                $values = collect($this->filterValues($params[$definition['key']] ?? []))
                    ->map(fn ($value) => CatalogFilterValue::facetKey($column, $value))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [$definition['key'] => $values];
            })
            ->all();
        $response = [];

        foreach (self::CHARACTERISTIC_FACETS as $column => $definition) {
            $buckets = [];

            foreach ($rows as $row) {
                if ( ! $this->rowMatchesCharacteristicSelections($row, $selections, $definition['key'])) {
                    continue;
                }

                foreach (CatalogFilterValue::facetValues($column, $row->{$column}) as $label) {
                    $value = CatalogFilterValue::key($label);

                    if ($value === '' || $label === '') {
                        continue;
                    }

                    if ( ! isset($buckets[$value])) {
                        $buckets[$value] = [
                            'value' => $value,
                            'label' => $label,
                            'count' => 0,
                        ];
                    }

                    $buckets[$value]['count'] += (int) $row->aggregate;
                }
            }

            // A selected zero-result value must remain visible so it can be
            // unticked; unavailable unselected values disappear entirely.
            foreach ($selections[$definition['key']] as $selectedValue) {
                if (isset($buckets[$selectedValue])) {
                    continue;
                }

                $label = CatalogFilterValue::facetDisplay($column, $selectedValue);
                $buckets[$selectedValue] = [
                    'value' => $selectedValue,
                    'label' => $label !== '' ? $label : Str::ucfirst($selectedValue),
                    'count' => 0,
                ];
            }

            $items = collect($buckets)
                ->sort(function (array $left, array $right) {
                    return $right['count'] <=> $left['count']
                        ?: strcasecmp($left['label'], $right['label']);
                })
                ->values()
                ->all();

            if ($items) {
                $response[] = [
                    'key' => $definition['key'],
                    'title' => $definition['title'],
                    'items' => $items,
                ];
            }
        }

        return $response;
    }


    /**
     * Check one grouped product row against every selected facet except the
     * facet currently being counted.
     */
    private function rowMatchesCharacteristicSelections($row, array $selections, string $exceptFacet): bool
    {
        foreach (self::CHARACTERISTIC_FACETS as $column => $definition) {
            $selected = $selections[$definition['key']] ?? [];

            if ($definition['key'] === $exceptFacet || ! $selected) {
                continue;
            }

            $available = collect(CatalogFilterValue::facetValues($column, $row->{$column}))
                ->map(fn ($value) => CatalogFilterValue::key($value));

            if ($available->intersect($selected)->isEmpty()) {
                return false;
            }
        }

        return true;
    }


    /**
     * Keep the short-lived cache key stable regardless of checkbox order.
     */
    private function characteristicFacetCacheParams(array $params): array
    {
        $normalized = [
            'ids' => $params['ids'] ?? '',
            'group' => trim((string) ($params['group'] ?? '')),
            'cat' => $this->categoryId($params['cat'] ?? null),
            'subcat' => $this->categoryId($params['subcat'] ?? null),
            'start' => trim((string) ($params['start'] ?? '')),
            'end' => trim((string) ($params['end'] ?? '')),
        ];

        foreach ([
            'author' => ! empty($params['autor']) ? $params['autor'] : ($params['author'] ?? []),
            'publisher' => ! empty($params['nakladnik']) ? $params['nakladnik'] : ($params['publisher'] ?? []),
        ] as $key => $rawValues) {
            $values = $this->entitySlugs($rawValues);
            sort($values);
            $normalized[$key] = $values;
        }

        foreach (self::CHARACTERISTIC_FACETS as $column => $definition) {
            $values = collect($this->filterValues($params[$definition['key']] ?? []))
                ->map(fn ($value) => CatalogFilterValue::facetKey($column, $value))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
            $normalized[$definition['key']] = $values;
        }

        return $normalized;
    }


    private function entityFacetCacheKey(array $params, string $type): string
    {
        $cacheParams = $this->characteristicFacetCacheParams($params);
        $cacheParams['type'] = $type;
        $cacheParams['search'] = trim((string) ($params[
            $type === 'author' ? 'search_author' : 'search_publisher'
        ] ?? ''));

        return 'catalog.' . $type . '-facet:' . sha1(json_encode(
            $cacheParams,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
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
    private function productContextQuery(array $params, $exceptFacet = null): Builder
    {
        $query = Product::query()->active()->hasStock();
        $excludedFacets = is_array($exceptFacet) ? $exceptFacet : array_filter([$exceptFacet]);

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

        $authorSlugs = $this->entitySlugs(
            ! empty($params['autor']) ? $params['autor'] : ($params['author'] ?? [])
        );
        if ( ! in_array('autor', $excludedFacets, true) && $authorSlugs) {
            $query->whereIn('author_id', Author::query()->select('id')->whereIn('slug', $authorSlugs));
        }

        $publisherSlugs = $this->entitySlugs(
            ! empty($params['nakladnik']) ? $params['nakladnik'] : ($params['publisher'] ?? [])
        );
        if ( ! in_array('nakladnik', $excludedFacets, true) && $publisherSlugs) {
            $query->whereIn('publisher_id', Publisher::query()->select('id')->whereIn('slug', $publisherSlugs));
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
            if (in_array($parameter, $excludedFacets, true) || empty($params[$parameter])) {
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
