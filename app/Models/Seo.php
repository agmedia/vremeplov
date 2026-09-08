<?php

namespace App\Models;

use App\Helpers\Metatags;
use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\Publisher;
use Illuminate\Http\Request;

/**
 * Class Sitemap
 * @package App\Models
 */
class Seo
{

    /**
     * @param Product $product
     *
     * @return string[]
     */
    public static function getProductData(Product $product): array
    {
        $author = isset($product->author->title) ? trim((string) $product->author->title) : '';
        $title = trim((string) ($product->meta_title ?: $product->name));
        $description = trim(strip_tags((string) $product->meta_description));

        if ($description === '') {
            $description = trim($product->name . ($author !== '' ? ' — ' . $author : ''))
                . '. Provjerite cijenu, stanje i dostupnost u Antikvarijatu Vremeplov.';
        }

        return [
            'title' => $title . ($author !== '' && stripos($title, $author) === false ? ' - ' . $author : ''),
            'description' => mb_substr($description, 0, 160),
        ];
    }


    /**
     * @param Author        $author
     * @param Category|null $cat
     * @param Category|null $subcat
     *
     * @return array
     */
    public static function getAuthorData(Author $author, ?Category $cat = null, ?Category $subcat = null): array
    {
        $title = trim((string) ($author->meta_title ?: $author->title));
        $description = trim(strip_tags((string) ($author->meta_description ?: $author->description)));

        if ($description === '') {
            $description = 'Dostupni naslovi autora ' . $author->title . ' u ponudi Antikvarijata Vremeplov.';
        }

        if ($cat) {
            $title .= ' – ' . ($cat->meta_title ?: $cat->title);
            $description = 'Naslovi autora ' . $author->title . ' u kategoriji ' . $cat->title . '.';
        }

        if ($subcat) {
            $title = trim((string) ($author->meta_title ?: $author->title)) . ' – ' . ($subcat->meta_title ?: $subcat->title);
            $description = 'Naslovi autora ' . $author->title . ' u kategoriji ' . $subcat->title . '.';
        }

        $canonical = route('catalog.route.author', [
            'author' => $author,
            'cat' => $cat,
            'subcat' => $subcat,
        ]);

        return [
            'title'       => $title,
            'description' => mb_substr($description, 0, 160),
            'canonical'   => $canonical,
            'tags'        => []
        ];
    }


    /**
     * @param Publisher     $publisher
     * @param Category|null $cat
     * @param Category|null $subcat
     *
     * @return array
     */
    public static function getPublisherData(Publisher $publisher, ?Category $cat = null, ?Category $subcat = null): array
    {
        $title = trim((string) ($publisher->meta_title ?: $publisher->title));
        $description = trim(strip_tags((string) ($publisher->meta_description ?: $publisher->description)));

        if ($description === '') {
            $description = 'Dostupna izdanja nakladnika ' . $publisher->title . ' u ponudi Antikvarijata Vremeplov.';
        }

        if ($cat) {
            $title .= ' – ' . ($cat->meta_title ?: $cat->title);
            $description = 'Izdanja nakladnika ' . $publisher->title . ' u kategoriji ' . $cat->title . '.';
        }

        if ($subcat) {
            $title = trim((string) ($publisher->meta_title ?: $publisher->title)) . ' – ' . ($subcat->meta_title ?: $subcat->title);
            $description = 'Izdanja nakladnika ' . $publisher->title . ' u kategoriji ' . $subcat->title . '.';
        }

        $canonical = route('catalog.route.publisher', [
            'publisher' => $publisher,
            'cat' => $cat,
            'subcat' => $subcat,
        ]);

        return [
            'title'       => $title,
            'description' => mb_substr($description, 0, 160),
            'canonical'   => $canonical,
            'tags'        => []
        ];
    }


    /**
     * @param Request $request
     * @param string  $target
     *
     * @return array
     */
    public static function getMetaTags(Request $request, string $target = 'product'): array
    {
        $response = [];
        $data = $request->toArray();

        if ($target == 'filter') {
            if (array_key_exists('start', $data) || array_key_exists('end', $data) || array_key_exists('autor', $data) || array_key_exists('nakladnik', $data) || array_key_exists('sort', $data)) {
                array_push($response, Metatags::noFollow());
            }
        }

        if ($target == 'ap_filter') {
            if (array_key_exists('letter', $data)) {
                array_push($response, Metatags::noFollow());
            }
        }

        return $response;
    }


}
