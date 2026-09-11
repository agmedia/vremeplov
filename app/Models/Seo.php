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

        return [
            'title' => $title . ($author !== '' && stripos($title, $author) === false ? ' - ' . $author : ''),
            'description' => self::productDescription($product, 160),
        ];
    }


    /**
     * Build a useful description of this exact physical copy.
     *
     * Manual metadata remains the editorial lead, while condition and other
     * copy-specific facts keep otherwise identical editions distinguishable.
     */
    public static function productDescription(Product $product, ?int $limit = 160): string
    {
        $manualMeta = self::normalizeDescription($product->meta_description);
        $body = self::normalizeDescription($product->description);
        $hasEditorialLead = $manualMeta !== '' || $body !== '';
        $author = isset($product->author->title) ? self::normalizeDescription($product->author->title) : '';
        $lead = $manualMeta ?: $body;

        // A large part of the legacy catalog used the product title itself as
        // an all-caps description. Keep the wording but use the calmer public
        // card title so Merchant Center and snippets are editorially clean.
        if ($manualMeta === '' && $body !== '' && self::sameText($body, $product->name)) {
            $lead = self::normalizeDescription($product->card_name);
        }

        if ($lead === '') {
            $lead = trim(self::normalizeDescription($product->name) . ($author !== '' ? ' — ' . $author : ''));
        }

        $details = self::productDetailSegments($product, $lead, $manualMeta !== '');
        $detailText = implode('. ', $details);

        if ($detailText !== '') {
            $detailText .= '.';

            // Leave enough room for the condition/note so a long publisher
            // description cannot hide what is unique about this copy.
            if ($limit !== null && mb_strlen($lead . ' ' . $detailText, 'UTF-8') > $limit) {
                $reserved = min(80, mb_strlen($detailText, 'UTF-8'));
                $lead = self::limitDescription($lead, max(60, $limit - $reserved - 1));
            }

            $lead = rtrim($lead);
            $lead .= preg_match('/[.!?…]$/u', $lead) ? ' ' : '. ';
            $lead .= $detailText;
        } elseif (! $hasEditorialLead && $lead !== '') {
            $lead = rtrim($lead, " .\t\n\r\0\x0B")
                . '. Provjerite cijenu, stanje i dostupnost u Antikvarijatu Vremeplov.';
        }

        if ($lead === '') {
            $lead = 'Artikal iz ponude Antikvarijata Vremeplov.';
        }

        return $limit === null ? $lead : self::limitDescription($lead, $limit);
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
    public static function normalizeDescription($value): string
    {
        $description = preg_replace(
            '/<(?:br|\/p|\/div|\/li|\/h[1-6]|\/tr)\b[^>]*>/iu',
            ' ',
            (string) $value
        );
        $description = html_entity_decode(strip_tags($description ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = str_replace("\u{00A0}", ' ', $description);
        $description = preg_replace(
            '/(?<!\s)(?=(?:Broj\s+stranica|Jezik|Pismo|Godina(?:\s+izdanja)?|Uvez|Stanje|Nakladnik|Izdavač|Izdavac|Autor|ISBN|ISSN|EAN)\s*:)/iu',
            ' ',
            $description
        );

        return trim(preg_replace('/\s+/u', ' ', $description) ?? '');
    }


    /**
     * Keep the most useful copy-specific facts close to the beginning.
     */
    private static function productDetailSegments(Product $product, string $lead, bool $hasManualMeta): array
    {
        $publisher = isset($product->publisher->title)
            ? self::normalizeDescription($product->publisher->title)
            : '';
        $values = [
            'Stanje' => $product->condition,
            'Napomena' => $product->note,
        ];

        // A manually curated meta description only needs the facts that make
        // the physical copy distinct. Generated descriptions can use all the
        // available bibliographic data.
        if (! $hasManualMeta) {
            $values += [
                'Šifra primjerka' => $product->sku,
                'Izdavač' => $publisher,
                'Godina izdanja' => $product->year,
                'Uvez' => $product->binding,
                'Broj stranica' => $product->pages,
                'Dimenzije' => $product->dimensions,
                'Jezik' => $product->origin,
                'Pismo' => $product->letter,
            ];
        }

        $segments = [];

        foreach ($values as $label => $value) {
            $value = self::normalizeDescription($value);

            if ($value === '' || $value === '-' || self::containsValue($lead, $value)) {
                continue;
            }

            $segments[] = $label . ': ' . $value;
        }

        return $segments;
    }


    private static function containsValue(string $description, string $value): bool
    {
        return mb_stripos($description, $value, 0, 'UTF-8') !== false;
    }


    private static function sameText(string $first, $second): bool
    {
        $normalize = static function ($value): string {
            $value = self::normalizeDescription($value);
            $value = mb_strtolower($value, 'UTF-8');

            return trim($value, " .,:;!?–—-\t\n\r\0\x0B");
        };

        return $normalize($first) !== '' && $normalize($first) === $normalize($second);
    }


    /**
     * Limit search snippets without leaving a cut-off word at the end.
     */
    public static function limitDescription(string $description, int $limit): string
    {
        $description = trim($description);

        if ($limit < 2 || mb_strlen($description, 'UTF-8') <= $limit) {
            return $description;
        }

        $truncated = mb_substr($description, 0, $limit - 1, 'UTF-8');
        $wordSafe = preg_replace('/\s+\S*$/u', '', $truncated);

        if (is_string($wordSafe) && trim($wordSafe) !== '') {
            $truncated = $wordSafe;
        }

        return rtrim($truncated, " ,;:.!?–—-\t\n\r\0\x0B") . '…';
    }


}
