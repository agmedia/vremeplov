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
        $description = self::normalizeDescription($product->meta_description);

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
        $authorTitle = trim((string) $author->title);
        $title = preg_replace('/^[\s,;:]+/u', '', trim((string) ($author->meta_title ?: $authorTitle))) ?? $authorTitle;
        $description = self::normalizeDescription($author->meta_description ?: $author->description);

        if ($description === '') {
            $description = 'Dostupni naslovi autora ' . $authorTitle . ' u ponudi Antikvarijata Vremeplov.';
        }

        if ($cat) {
            $title .= ' – ' . ($cat->meta_title ?: $cat->title);
            $description = 'Naslovi autora ' . $authorTitle . ' u kategoriji ' . $cat->title . '.';
        }

        if ($subcat) {
            $title = preg_replace('/^[\s,;:]+/u', '', trim((string) ($author->meta_title ?: $authorTitle))) . ' – ' . ($subcat->meta_title ?: $subcat->title);
            $description = 'Naslovi autora ' . $authorTitle . ' u kategoriji ' . $subcat->title . '.';
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
        $description = self::normalizeDescription($publisher->meta_description ?: $publisher->description);

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
            if (collect(['start', 'end', 'autor', 'nakladnik', 'pismo', 'stanje', 'uvez', 'jezik', 'sort'])->contains(fn ($key) => array_key_exists($key, $data))) {
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


    /**
     * Turn legacy rich-text descriptions into readable search snippets.
     */
    private static function normalizeDescription($value): string
    {
        $description = preg_replace(
            '/<(?:br|\/p|\/div|\/li|\/h[1-6]|\/tr)\b[^>]*>/iu',
            ' ',
            (string) $value
        );
        $description = html_entity_decode(strip_tags($description ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = str_replace("\u{00A0}", ' ', $description);

        return trim(preg_replace('/\s+/u', ' ', $description) ?? '');
    }


}
