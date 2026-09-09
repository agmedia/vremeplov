<?php

namespace App\Helpers;

use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\Publisher;
use App\Support\CatalogFilterValue;
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
    public function category($group, ?Category $cat = null, $subcat = null)
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
    public function product($group, ?Category $cat = null, $subcat = null, ?Product $prod = null)
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
    public function author(?Author $author = null, ?Category $cat = null, $subcat = null)
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
    public function publisher(?Publisher $publisher = null, ?Category $cat = null, $subcat = null)
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
    public function productBookSchema(?Product $prod = null)
    {
        if ($prod) {
            $approvedReviews = $prod->reviews()->take(5)->get();
            $reviewCount = $prod->reviews()->count();
            $averageRating = $reviewCount ? round((float) $prod->reviews()->avg('stars'), 2) : null;
            $isBook = $prod->group === 'knjige';
            $description = trim(strip_tags((string) ($prod->meta_description ?: $prod->description)))
                ?: $prod->name . ' u ponudi Antikvarijata Vremeplov.';

            $schema = [
                '@context' => 'https://schema.org',
                '@type' => $isBook ? ['Book', 'Product'] : 'Product',
                '@id' => url($prod->url) . '#product',
                'description' => $description,
                'image' => $prod->image,
                'name' => $prod->name,
                'url' => url($prod->url),
                'category' => ucfirst(str_replace('-', ' ', (string) $prod->group)),
                'offers' => [
                    '@type' => 'Offer',
                    'url' => url($prod->url),
                    'priceCurrency' => 'EUR',
                    'price' => number_format((float) (($prod->special()) ?: $prod->price), 2, '.', ''),
                    'availability' => ($prod->quantity) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'itemCondition' => 'https://schema.org/UsedCondition',
                    'seller' => [
                        '@type' => 'Organization',
                        '@id' => url('/#organization'),
                        'name' => 'Antikvarijat Vremeplov',
                    ],
                ],
            ];

            $sku = trim((string) $prod->sku);
            if ($sku !== '' && $sku !== '-') {
                $schema['sku'] = $sku;
            }

            if ($isBook && $prod->publisher && (int) $prod->publisher_id !== (int) config('settings.unknown_publisher')) {
                $schema['publisher'] = [
                    '@type' => 'Organization',
                    'name' => $prod->publisher->title,
                    'url' => url($prod->publisher->url),
                ];
            }

            if ($isBook && $prod->author && (int) $prod->author_id !== (int) config('settings.unknown_author')) {
                $schema['author'] = [
                    '@type' => 'Person',
                    'name' => $prod->author->title,
                    'url' => url($prod->author->url),
                ];
            }

            if ($isBook && $prod->year) {
                $schema['datePublished'] = (string) $prod->year;
            }

            $isbn = preg_replace('/[^0-9Xx]/', '', (string) $prod->isbn);
            if ($isBook && in_array(strlen($isbn), [10, 13], true)) {
                $schema['isbn'] = strtoupper($isbn);
            }

            if ($isBook && ctype_digit(trim((string) $prod->pages))) {
                $schema['numberOfPages'] = (int) $prod->pages;
            }

            if ($isBook && $prod->origin) {
                $languages = [
                    'hrvatski' => 'hr',
                    'engleski' => 'en',
                    'njemački' => 'de',
                    'nemački' => 'de',
                    'talijanski' => 'it',
                    'italijanski' => 'it',
                    'francuski' => 'fr',
                    'srpski' => 'sr',
                    'slovenski' => 'sl',
                ];
                $schemaLanguages = collect(CatalogFilterValue::facetValues('origin', $prod->origin))
                    ->map(function ($language) use ($languages) {
                        return $languages[mb_strtolower($language)] ?? $language;
                    })
                    ->values()
                    ->all();

                if (count($schemaLanguages) === 1) {
                    $schema['inLanguage'] = $schemaLanguages[0];
                } elseif ($schemaLanguages) {
                    $schema['inLanguage'] = $schemaLanguages;
                }
            }

            if ($isBook && $prod->binding) {
                $binding = mb_strtolower((string) $prod->binding);

                if (str_contains($binding, 'tvrdi')) {
                    $schema['bookFormat'] = 'https://schema.org/Hardcover';
                } elseif (str_contains($binding, 'meki')) {
                    $schema['bookFormat'] = 'https://schema.org/Paperback';
                }
            }

            $additionalProperties = collect([
                'Godina izdanja' => ! $isBook ? $prod->year : null,
                (count(CatalogFilterValue::facetValues('origin', $prod->origin)) > 1 ? 'Jezici' : 'Jezik') => $prod->origin,
                'Broj stranica' => $prod->pages,
                'Dimenzije' => $prod->dimensions ? $prod->dimensions . ' cm' : null,
                'Stanje' => $prod->condition,
                'Uvez' => $prod->binding,
            ])->filter(fn ($value) => trim((string) $value) !== '')
                ->map(function ($value, $name) {
                    return [
                        '@type' => 'PropertyValue',
                        'name' => $name,
                        'value' => (string) $value,
                    ];
                })->values()->toArray();

            if ($additionalProperties) {
                $schema['additionalProperty'] = $additionalProperties;
            }

            if ($reviewCount > 0 && $averageRating !== null) {
                $schema['aggregateRating'] = [
                    '@type' => 'AggregateRating',
                    'ratingValue' => $averageRating,
                    'reviewCount' => $reviewCount,
                    'bestRating' => 5,
                    'worstRating' => 1,
                ];
            }

            if ($approvedReviews->count()) {
                $schema['review'] = $approvedReviews->map(function ($review) {
                    return [
                        '@type' => 'Review',
                        'author' => [
                            '@type' => 'Person',
                            'name' => trim(($review->fname ?: '') . ' ' . ($review->lname ?: '')) ?: 'Kupac',
                        ],
                        'datePublished' => optional($review->created_at)->format('Y-m-d'),
                        'reviewBody' => trim(strip_tags((string) $review->message)),
                        'reviewRating' => [
                            '@type' => 'Rating',
                            'ratingValue' => (float) $review->stars,
                            'bestRating' => 5,
                            'worstRating' => 1,
                        ],
                    ];
                })->values()->toArray();
            }

            return $schema;
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
