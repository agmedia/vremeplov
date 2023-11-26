<?php

namespace App\Models\Front\Catalog;

use App\Models\Front\Catalog\Category;
use App\Helpers\Helper;
use App\Helpers\ProductHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class Publisher
 * @package App\Models\Front\Catalog
 */
class Publisher extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'publishers';

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
        return $this->hasMany(Product::class, 'publisher_id', 'id')->active()->hasStock();
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
        $query = (new Publisher())->newQuery();

        if ($request['search_publisher']) {
            $query->active();

            $query = Helper::searchByTitle($query, $request['search_publisher']);

        } else {
            $query->active()->featured();

            if ($request['group'] && ! $request['search_publisher']) {
                $query->whereHas('products', function ($query) use ($request) {
                    $query = ProductHelper::queryCategories($query, $request);

                    if ($request['author']) {
                        if (strpos($request['author'], '+') !== false) {
                            $arr = explode('+', $request['author']);
                            $pubs = Author::query()->whereIn('slug', $arr)->pluck('id');

                            $query->whereIn('author_id', $pubs);
                        } else {
                            $query->where('author_id', $request['author']);
                        }
                    }
                });
            }

            if ($request['author'] && ! $request['group']) {
                $query->whereHas('products', function ($query) use ($request) {
                    $query = ProductHelper::queryCategories($query, $request);
                    $query->where('author_id', Author::where('slug', $request['author'])->pluck('id')->first());
                });
            }

            if ($request['ids'] && $request['ids'] != '') {
                $_ids = collect(explode(',', substr($request['ids'], 1, -1)))->unique();

                $query->whereHas('products', function ($query) use ($_ids) {
                    $query->active()->hasStock()->whereIn('id', $_ids);
                });
            }
        }

        return $query->limit($limit)->orderBy('title');
    }


    /**
     * @return Collection
     */
    public static function letters(): Collection
    {
        $letters = collect();
        $publishers = Publisher::active()->pluck('letter')->unique();

        foreach (Helper::abc() as $item) {
            if ($item == $publishers->contains($item)) {
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
            $query =$query->topList()->select('id', 'title', 'slug')->whereHas('products', function ($query) {
                $query->where('publisher_id', $this->id);
            });

        } else {
            $query = $query->whereHas('products', function ($query) {
                $query->where('publisher_id', $this->id);
            })->where('parent_id', $id);
        }

        return $query/*->withCount(['products as products_count' => function ($query) {
                         $query->where('publisher_id', $this->id);
                     }])
                     */->orderBy('title')
                     ->get();
    }

}
