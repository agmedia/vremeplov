<?php

namespace App\Helpers;

use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Product;
use App\Models\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RouteResolver
{

    private $request;

    public $group;
    public $category;
    public $subcategory;
    public $product;
    //
    public $title;
    public $description;
    public $canonical;

    private $all_path;

    /**
     * RouteResolver constructor.
     */
    public function __construct(Request $request, $group, $cat = null, $subcat = null, Product $prod = null)
    {
        $this->request = $request;
        $this->group = $group;
        $this->category = $cat;
        $this->subcategory = $subcat;
        $this->product = $prod;
        $this->all_path = Str::slug(config('settings.group_path'));
    }


    /**
     * @return bool
     */
    public function isUnwantedRoute(): bool
    {
        if ($this->group == 'media') {
            return true;
        }

        return false;
    }


    public function isAllowedGroup()
    {
        if ($this->group) {
            $groups = Category::getGroups();
            $group_exist = false;

            foreach ($groups as $item) {
                if ($item->slug == $this->group) {
                    $this->title = $item->title;
                    $this->description = $item->title . ' - Antikvarijat Vremeplov';
                    $this->canonical = url($item->slug);
                    $group_exist = true;
                }
            }

            if ($this->group == $this->all_path) {
                $this->group = null;
                $this->title = 'Web shop';
                $this->description = 'Dobro došli na stranice antikvarijata Vremeplov. Specijalizirani smo za stare razglednice, pisma, knjige, plakate,časopise te vršimo otkup i prodaju navedenih.';
                $this->canonical = url($this->all_path);
                $group_exist = true;
            }

            if ( ! $group_exist) {
                $product = Product::query()->where('slug', $this->group)->first();

                if ( ! $product) {
                    abort(404);
                }

                $this->product = $product;
            }
        }

        return $this;
    }


    public function setRoute()
    {
        // Ako je grupa i kategorija_ili_artikl
        if ( ! $this->product && ! $this->subcategory && $this->category) {
            $category = Category::query()->where('slug', $this->category)->where('parent_id', 0)->first();

            if ( ! $category) {
                $this->product = Product::where('slug', $this->category)->first();

                if ( ! $this->product) {
                    abort(404);
                }

            } else {
                $this->title = $category->title;
                $this->description = $category->meta_description;
                $this->canonical = url($this->group . '/' . $category->slug);
            }

            $this->category = $category;
        }

        // Ako je grupa, kategorija, podkategorija_ili_artikl
        if ( ! $this->product && $this->subcategory && $this->category) {
            $category = Category::query()->where('slug', $this->category)->where('parent_id', 0)->first();

            if ( ! $category) {
                abort(404);
            }

            $subcategory = Category::where('slug', $this->subcategory)->where('parent_id', $category->id)->first();

            if ( ! $subcategory) {
                $this->product = Product::where('slug', $this->subcategory)->first();

                if ( ! $this->product) {
                    abort(404);
                }

            } else {
                $this->title = $subcategory->title;
                $this->description = $subcategory->meta_description;
                $this->canonical = url($this->group . '/' . $category->slug . '/' . $subcategory->slug);
            }

            $this->category = $category;
            $this->subcategory = $subcategory;
        }

        if ($this->product && $this->subcategory && $this->category) {
            $category = Category::query()->where('slug', $this->category)->where('parent_id', 0)->first();

            if ( ! $category) {
                abort(404);
            }

            $subcategory = Category::where('slug', $this->subcategory)->where('parent_id', $category->id)->first();

            if ( ! $subcategory) {
                abort(404);
            }

            if ( ! isset($this->product->id)) {
                abort(404);
            }

            $this->category = $category;
            $this->subcategory = $subcategory;
        }

        return $this;
    }


    /**
     * @return \stdClass
     */
    public function setData(): \stdClass
    {
        $data = new \stdClass();

        $data->group = $this->group;
        $data->category = $this->category;
        $data->subcategory = $this->subcategory;

        return $data;
    }


    /**
     * @return \stdClass
     */
    public function setMeta(): array
    {
        $data = [];

        $data['title'] = $this->title;
        $data['description'] = $this->description;
        $data['canonical'] = $this->canonical;
        $data['tags'] = Seo::getMetaTags($this->request, 'filter');

        return $data;
    }
}
