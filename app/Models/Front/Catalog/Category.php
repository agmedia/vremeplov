<?php

namespace App\Models\Front\Catalog;

use App\Helpers\Helper;
use App\Models\Back\Settings\Settings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class Category extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'categories';

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
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function parent()
    {
        return $this->hasOne(Category::class, 'id', 'parent_id');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function products()
    {
        return $this->hasManyThrough(Product::class, CategoryProducts::class, 'category_id', 'id', 'id', 'product_id')->where('status', 1)->where('quantity', '>', 0)->orderBy('sort_order');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subcategories()
    {
        return $this->hasMany(Category::class, 'parent_id', 'id')->where('status', 1)->orderBy('title');
    }


    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1)->where('title', '!=', '');
    }


    /**
     * @param Builder $query
     * @param string  $group
     *
     * @return Builder
     */
    public function scopeTopList(Builder $query, string $group = ''): Builder
    {
        if ( ! empty($group)) {
            return $query->where('group', $group)->where('parent_id', '==', 0);
        }

        return $query->where('parent_id', '==', 0);
    }


    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeGroups(Builder $query): Builder
    {
        return $query->groupBy('group');
    }


    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeSortByName(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc');
    }


    /**
     * @param Category|null $subcategory
     *
     * @return string
     */
    public function url(Category $subcategory = null)
    {
        if ($subcategory) {
            return route('catalog.route', [
                'group' => Str::slug($this->group),
                'cat' => $this,
                'subcat' => $subcategory
            ]);
        }

        return route('catalog.route', [
            'group' => Str::slug($this->group),
            'cat' => $this
        ]);
    }


    /**
     * @return Collection
     */
    public static function getGroups(): Collection
    {
        return Helper::resolveCache('cat')->remember('groups', config('cache.one_day'), function () {
            return Settings::get('category', 'list.groups')->where('status', 1)->sortBy('sort_order');
        });
    }

}
