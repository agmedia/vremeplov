<?php

namespace App\Helpers;

use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\Publisher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Breadcrumb
{

    /**
     * @var array
     */
    private $schema = [];

    /**
     * @var array
     */
    private $breadcrumbs = [];


    /**
     * Breadcrumb constructor.
     */
    public function __construct()
    {
        $this->setDefault();
    }


    /**
     * @param               $group
     * @param Category|null $cat
     * @param null          $subcat
     *
     * @return $this
     */
    public function category($group, Category $cat = null, $subcat = null)
    {
        if (isset($group) && $group) {
            $this->addGroup($group);

            if ($cat) {
                array_push($this->breadcrumbs, [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $cat->title,
                    'item' => route('catalog.route', ['group' => $group, 'cat' => $cat])
                ]);
            }

            if ($subcat) {
                array_push($this->breadcrumbs, [
                    '@type' => 'ListItem',
                    'position' => 4,
                    'name' => $subcat->title,
                    'item' => route('catalog.route', ['group' => $group, 'cat' => $cat, 'subcat' => $subcat])
                ]);
            }
        }

        if ( ! $group) {
            $this->addGroup('web shop');
        }

        return $this;
    }


    /**
     * @param               $group
     * @param Category|null $cat
     * @param null          $subcat
     * @param Product|null  $prod
     *
     * @return $this
     */
    public function product($group, Category $cat = null, $subcat = null, Product $prod = null)
    {
        $this->category($group, $cat, $subcat);

        if ($prod) {
            $count = count($this->breadcrumbs) + 1;

            array_push($this->breadcrumbs, [
                '@type' => 'ListItem',
                'position' => $count,
                'name' => $prod->name,
                'item' => url($prod->url)
            ]);
        }

        return $this;
    }


    /**
     * @param Author|null   $author
     * @param Category|null $cat
     * @param               $subcat
     *
     * @return $this
     */
    public function author(Author $author = null, Category $cat = null, $subcat = null)
    {
        array_push($this->breadcrumbs, [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => 'Autori',
            'item' => route('catalog.route.author')
        ]);

        if ($author) {
            array_push($this->breadcrumbs, [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $author->title,
                'item' => route('catalog.route.author', ['author' => $author])
            ]);

            if ($cat) {
                array_push($this->breadcrumbs, [
                    '@type' => 'ListItem',
                    'position' => 4,
                    'name' => $cat->title,
                    'item' => route('catalog.route.author', ['author' => $author, 'cat' => $cat])
                ]);
            }

            if ($subcat) {
                array_push($this->breadcrumbs, [
                    '@type' => 'ListItem',
                    'position' => 5,
                    'name' => $subcat->title,
                    'item' => route('catalog.route.author', ['author' => $author, 'cat' => $cat, 'subcat' => $subcat])
                ]);
            }
        }

        return $this;
    }


    /**
     * @param Publisher|null $publisher
     * @param Category|null  $cat
     * @param                $subcat
     *
     * @return $this
     */
    public function publisher(Publisher $publisher = null, Category $cat = null, $subcat = null)
    {
        array_push($this->breadcrumbs, [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => 'Izdavači',
            'item' => route('catalog.route.publisher')
        ]);

        if ($publisher) {
            array_push($this->breadcrumbs, [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $publisher->title,
                'item' => route('catalog.route.publisher', ['publisher' => $publisher])
            ]);

            if ($cat) {
                array_push($this->breadcrumbs, [
                    '@type' => 'ListItem',
                    'position' => 4,
                    'name' => $cat->title,
                    'item' => route('catalog.route.publisher', ['publisher' => $publisher, 'cat' => $cat])
                ]);
            }

            if ($subcat) {
                array_push($this->breadcrumbs, [
                    '@type' => 'ListItem',
                    'position' => 5,
                    'name' => $subcat->title,
                    'item' => route('catalog.route.publisher', ['publisher' => $publisher, 'cat' => $cat, 'subcat' => $subcat])
                ]);
            }
        }

        return $this;
    }


    /**
     * @param Product|null $prod
     *
     * @return array
     */
    public function productBookSchema(Product $prod = null)
    {
        if ($prod) {
            return [
                '@context' => 'https://schema.org/',
                '@type' => 'Book',
                'datePublished' => $prod->year ?: '...',
                'description' => $prod->name . ' knjiga autora ' . (($prod->author) ? $prod->author->title : 'Autor') . ' godine izdanja ' . ($prod->year ?: '...') . '. izdavača ' . (($prod->publisher) ? $prod->publisher->title : 'Izdavačka kuća'),
                'image' => asset($prod->image),
                'name' => $prod->name,
                'url' => url($prod->url),
                'publisher' => [
                    '@type' => 'Organization', 
                    'name' => ($prod->publisher) ? $prod->publisher->title : 'Izdavačka kuća',
                ],
                'author' => ($prod->author) ? $prod->author->title : 'Autor',
                'offers' => [
                    '@type' => 'Offer',
                    'priceCurrency' => 'HRK',
                    'price' => ($prod->special()) ? $prod->special() : number_format($prod->price, 2, '.', ''),
                    'sku' => $prod->sku,
                    'availability' => ($prod->quantity) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'
                ],
            ];
        }
    }


    /**
     * @return array
     */
    public function resolve()
    {
        $this->schema['itemListElement'] = $this->breadcrumbs;

        return $this->schema;
    }


    /**
     *
     */
    private function setDefault()
    {
        $this->schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'BreadcrumbList'
        ];

        array_push($this->breadcrumbs, [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Naslovnica',
            'item' => route('index')
        ]);
    }


    /**
     * @param $group
     */
    public function addGroup($group)
    {
        array_push($this->breadcrumbs, [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => Str::ucfirst($group),
            'item' => route('catalog.route', ['group' => $group])
        ]);
    }
}
