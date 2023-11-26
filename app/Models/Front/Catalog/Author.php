<?php

namespace App\Models\Front\Catalog;

use App\Helpers\Helper;
use App\Helpers\ProductHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Class Author
 * @package App\Models\Front\Catalog
 */
class Author extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'authors';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];


    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'author_id', 'id')->active()->hasStock();
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', 1);
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeBasicData($query)
    {
        return $query->select('id', 'title', 'slug', 'url');
    }


    /**
     * @param array $request
     * @param int   $limit
     *
     * @return Builder
     */
    public function filter(array $request, int $limit = 20): Builder
    {
        $query = (new Author())->newQuery();

        if ($request['search_author']) {
            $query->active();

            $query = Helper::searchByTitle($query, $request['search_author']);

        } else {
            $query->active()->featured();

            if ($request['group'] && ! $request['search_author']) {
                $query->whereHas('products', function ($query) use ($request) {
                    $query = ProductHelper::queryCategories($query, $request);

                    if ($request['publisher']) {
                        if (strpos($request['publisher'], '+') !== false) {
                            $arr = explode('+', $request['publisher']);
                            $pubs = Publisher::query()->whereIn('slug', $arr)->pluck('id');

                            $query->whereIn('publisher_id', $pubs);
                        } else {
                            $query->where('publisher_id', $request['publisher']);
                        }
                    }
                });
            }

            if (! $request['group'] && $request['publisher']) {
                $query->whereHas('products', function ($query) use ($request) {
                    $query = ProductHelper::queryCategories($query, $request);
                    $query->where('publisher_id', Publisher::where('slug', $request['publisher'])->pluck('id')->first());
                });
            }

            if (! $request['group'] && $request['ids']) {
                $_ids = collect(explode(',', substr($request['ids'], 1, -1)))->unique();

                $query->whereHas('products', function ($query) use ($_ids) {
                    $query->active()->hasStock()->whereIn('id', $_ids);
                });
            }
        }

        $query->limit($limit)
              ->basicData()
              ->withCount('products')
              ->orderBy('title');

        return $query;
    }


    /**
     * @return Collection
     */
    public static function letters(): Collection
    {
        $letters = collect();
        $authors = Author::active()->pluck('letter')->unique();

        foreach (Helper::abc() as $item) {
            if ($item == $authors->contains($item)) {
                $letters->push([
                    'value' => $item,
                    'active' => true
                ]);
            } else {
                $letters->push([
                    'value' => $item,
                    'active' => false
                ]);
            }
        }

        return $letters;
    }


    /**
     * @param int $id
     *
     * @return Collection
     */
    public function categories(int $id = 0): Collection
    {
        $query = (new Category())->newQuery();

        $query->active();

        if ( ! $id) {
            $query->topList()->select('id', 'group', 'title', 'slug')->whereHas('products', function ($query) {
                $query->where('author_id', $this->id);
            });

        } else {
            $query->whereHas('products', function ($query) {
                $query->where('author_id', $this->id);
            })->where('parent_id', $id);
        }

        return $query/*->withCount(['products as products_count' => function ($query) {
                         $query->where('author_id', $this->id);
                     }])
                     */->sortByName()
                     ->get();
    }
}
