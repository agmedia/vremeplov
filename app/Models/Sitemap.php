<?php

namespace App\Models;

use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\Publisher;
use App\Models\Front\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Class Sitemap
 * @package App\Models
 */
class Sitemap
{

    /**
     * @var string|null
     */
    private $sitemap;

    /**
     * @var array
     */
    private $response = [];


    /**
     * Sitemap constructor.
     *
     * @param string|null $sitemap
     */
    public function __construct(string $sitemap = null)
    {
        $this->sitemap = $this->setSitemap($sitemap);
    }


    /**
     * @return string|null
     */
    public function getSitemap()
    {
        return $this->sitemap;
    }


    /**
     * @return array
     */
    public function getResponse(): array
    {
        return $this->response;
    }


    /**
     * @param string $sitemap
     *
     * @return array
     */
    private function setSitemap(string $sitemap)
    {
        if ( ! $sitemap) {
            return $sitemap;
        }

        if ($sitemap == 'pages' || $sitemap == 'pages.xml') {
            return $this->getPages();
        }

        if ($sitemap == 'categories' || $sitemap == 'categories.xml') {
            return $this->getCategories();
        }

        if ($sitemap == 'products' || $sitemap == 'products.xml') {
            return $this->getProducts();
        }

        if ($sitemap == 'authors' || $sitemap == 'authors.xml') {
            return $this->getAuthors();
        }

        if ($sitemap == 'publishers' || $sitemap == 'publishers.xml') {
            return $this->getPublishers();
        }

        if ($sitemap == 'images' || $sitemap == 'img') {
            return $this->getImages();
        }
    }


    /**
     * @return array
     */
    private function getImages(): array
    {
        $products = Product::query()->active()->hasStock()->select('url', 'id', 'image')->with('images');

        foreach ($products->get() as $product) {
            $this->response[$product->id] = [
                'loc' => url($product->url),
                'images' => [],
            ];

            if ($product->image) {
                $this->response[$product->id]['images'][] = [
                    'loc' => $this->absoluteImageUrl((string) $product->image)
                ];
            }

            foreach ($product->images as $image) {
                if ($image->image) {
                    $this->response[$product->id]['images'][] = [
                        'loc' => $this->absoluteImageUrl((string) $image->image)
                    ];
                }
            }
        }

        return $this->response;
    }

    private function absoluteImageUrl(string $path): string
    {
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $imageDomain = trim((string) config('settings.images_domain'));
        if ($imageDomain !== '') {
            return rtrim($imageDomain, '/') . '/' . ltrim($path, '/');
        }

        return asset(ltrim($path, '/'));
    }


    /**
     * @return array
     */
    private function getPages()
    {
        $pages = Page::query()->where('group', 'page')->where('slug', '!=', 'homepage')->where('status', '=', 1)->select('slug', 'status', 'updated_at')->get();
        $blogs = Page::query()->where('group', 'blog')->where('status', '=', 1)->select('slug', 'status', 'updated_at')->get();

        $this->response[] = [
            'url' => route('index'),
            'lastmod' => Carbon::now()->startOfMonth()->tz('UTC')->toAtomString()
        ];

        $this->response[] = [
            'url' => route('kontakt'),
            'lastmod' => Carbon::now()->startOfYear()->tz('UTC')->toAtomString()
        ];

        $this->response[] = [
            'url' => route('faq'),
            'lastmod' => Carbon::now()->startOfYear()->tz('UTC')->toAtomString()
        ];

        foreach ($pages as $page) {
            $this->response[] = [
                'url' => route('catalog.route.page', ['page' => $page->slug]),
                'lastmod' => $page->updated_at->tz('UTC')->toAtomString()
            ];
        }

        foreach ($blogs as $blog) {
            $this->response[] = [
                'url' => route('catalog.route.blog', ['blog' => $blog->slug]),
                'lastmod' => $blog->updated_at->tz('UTC')->toAtomString()
            ];
        }

        //dd($coll);

        return $this->response;
    }


    /**
     * @return array
     */
    private function getCategories()
    {
        $categories = Category::query()->active()->topList()->with('subcategories')->get();

        foreach ($categories as $category) {
            $this->response[] = [
                'url' => route('catalog.route', ['group' => Str::slug($category->group), 'cat' => $category->slug]),
                'lastmod' => $category->updated_at->tz('UTC')->toAtomString()
            ];

            foreach ($category->subcategories()->get() as $subcategory) {
                $this->response[] = [
                    'url' => route('catalog.route', ['group' => Str::slug($category->group), 'cat' => $category->slug, 'subcat' => $subcategory->slug]),
                    'lastmod' => $subcategory->updated_at->tz('UTC')->toAtomString()
                ];
            }
        }

        return $this->response;
    }


    /**
     * @return array
     */
    private function getProducts()
    {
        $products = Product::query()->active()->hasStock()->select('url', 'updated_at')->get();

        foreach ($products as $product) {
            $this->response[] = [
                'url' => url($product->url),
                'lastmod' => $product->updated_at->tz('UTC')->toAtomString()
            ];
        }

        return $this->response;
    }


    /**
     * @return array
     */
    private function getAuthors()
    {
        $authors = Author::query()->active()->select('url', 'updated_at')->get();

        $this->response[] = [
            'url' => route('catalog.route.author'),
            'lastmod' => Carbon::now()->startOfMonth()->tz('UTC')->toAtomString()
        ];

        foreach ($authors as $author) {
            $this->response[] = [
                'url' => url($author->url),
                'lastmod' => $author->updated_at->tz('UTC')->toAtomString()
            ];

            /*$cats = Category::query()->topList()->whereHas('products', function ($query) use ($author) {
                $query->where('author_id', $author->id);
            })->with('subcategories')->get();

            if ($cats) {
                foreach ($cats as $category) {
                    $this->response[] = [
                        'url' => route('catalog.route.author', ['author' => $author->slug, 'cat' => $category->slug]),
                        'lastmod' => $author->updated_at->tz('UTC')->toAtomString()
                    ];

                    foreach ($category->subcategories()->get() as $subcategory) {
                        $this->response[] = [
                            'url' => route('catalog.route.author', ['author' => $author->slug, 'cat' => $category->slug, 'subcat' => $subcategory->slug]),
                            'lastmod' => $author->updated_at->tz('UTC')->toAtomString()
                        ];
                    }
                }
            }*/
        }

        return $this->response;
    }


    /**
     * @return array
     */
    private function getPublishers()
    {
        $publishers = Publisher::query()->active()->select('url', 'updated_at')->get();

        $this->response[] = [
            'url' => route('catalog.route.publisher'),
            'lastmod' => Carbon::now()->startOfMonth()->tz('UTC')->toAtomString()
        ];

        foreach ($publishers as $publisher) {
            $this->response[] = [
                'url' => url($publisher->url),
                'lastmod' => $publisher->updated_at->tz('UTC')->toAtomString()
            ];
        }

        return $this->response;
    }
}
