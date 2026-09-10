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
    public function __construct(Request $request, $group, $cat = null, $subcat = null, ?Product $prod = null)
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
                    $this->description = 'Pregledajte dostupne artikle iz kategorije ' . $item->title . ' u ponudi Antikvarijata Vremeplov.';
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
        // Keep product URLs resolvable when a category slug is renamed,
        // including old links still present in caches or search engines.
        if ($this->product) {
            if ($this->isRequestedProductRoute($this->product)) {
                return $this->setProductRoute($this->product);
            }

            // Never pass unresolved route strings to product breadcrumbs. A
            // malformed product path is a 404, not a partially resolved page.
            abort(404);
        }

        // Ako je grupa i kategorija_ili_artikl
        if ( ! $this->product && ! $this->subcategory && $this->category) {
            $category = Category::query()->where('slug', $this->category)->where('parent_id', 0)->first();

            if ( ! $category) {
                $this->product = Product::where('slug', $this->category)->first();

                if ( ! $this->product) {
                    abort(404);
                }

            } else {
                $this->title = trim((string) $category->title);
                $this->description = $this->categoryDescription($category);
                $this->canonical = url($this->group . '/' . $category->slug);
            }

            $this->category = $category;
        }

        // Ako je grupa, kategorija, podkategorija_ili_artikl
        if ( ! $this->product && $this->subcategory && $this->category) {
            $category = Category::query()->where('slug', $this->category)->where('parent_id', 0)->first();

            if ( ! $category) {
                $product = Product::query()->where('slug', $this->subcategory)->first();

                if ($product && $this->isRequestedProductRoute($product)) {
                    return $this->setProductRoute($product);
                }

                abort(404);
            }

            $subcategory = Category::where('slug', $this->subcategory)->where('parent_id', $category->id)->first();

            if ( ! $subcategory) {
                $this->product = Product::where('slug', $this->subcategory)->first();

                if ( ! $this->product) {
                    abort(404);
                }

                if ($this->isRequestedProductRoute($this->product)) {
                    return $this->setProductRoute($this->product);
                }

            } else {
                $this->title = trim((string) $subcategory->title);
                $this->description = $this->categoryDescription($subcategory);
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
     * Match the product slug and catalog group while allowing an old category path.
     */
    private function isRequestedProductRoute(Product $product): bool
    {
        $storedPath = explode('/', trim((string) $product->url, '/'));
        $requestedPath = explode('/', trim($this->request->path(), '/'));

        $isLegacySingleSlug = count($requestedPath) === 1
            && end($requestedPath) === $product->slug;

        return $isLegacySingleSlug || (
            reset($storedPath) === reset($requestedPath)
            && end($requestedPath) === $product->slug
        );
    }


    /**
     * Resolve the current categories for breadcrumbs and related products.
     */
    private function setProductRoute(Product $product): self
    {
        $category = $product->category();
        $subcategory = $product->subcategory();

        if ($subcategory && ( ! $category || (int) $subcategory->parent_id !== (int) $category->id)) {
            $subcategory = null;
        }

        $this->product = $product;
        $this->group = $product->group ?: (explode('/', trim((string) $product->url, '/'))[0] ?? $this->group);
        $this->category = $category;
        $this->subcategory = $subcategory;

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


    private function categoryDescription(Category $category): string
    {
        $title = trim((string) $category->title);
        $normalizedTitle = mb_strtolower($title, 'UTF-8');
        $description = '';

        foreach ([$category->meta_description, $category->description] as $candidate) {
            $candidate = preg_replace('/<(?:br\s*\/?|\/p|\/div|\/li|\/h[1-6])>/iu', ' ', (string) $candidate) ?: (string) $candidate;
            $candidate = html_entity_decode(trim(strip_tags($candidate)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $candidate = trim(preg_replace('/\s+/u', ' ', $candidate) ?: $candidate);
            $normalizedCandidate = mb_strtolower(rtrim($candidate, " .\t\n\r\0\x0B"), 'UTF-8');
            $looksLikeLegacySeo = $candidate === ''
                || $normalizedCandidate === rtrim($normalizedTitle, '.')
                || mb_strtoupper($candidate, 'UTF-8') === $candidate
                || preg_match('/(?:antikvarijat\s+vremeplov|lopašićeva|zvonimirova\s+24|10000\s+zagreb|broj\s+telefona|01\s*\/\s*777|dodaj\s+u\s+košaricu|dodaj\s+u\s+kosaricu|mailto:)/iu', $candidate);

            if (! $looksLikeLegacySeo) {
                $description = $candidate;
                break;
            }
        }

        if ($description === '') {
            $templates = [
                'knjige' => 'Knjige iz kategorije %s: rabljena, rijetka i antikvarna izdanja.',
                'novine-i-casopisi' => 'Stare novine i časopisi iz kategorije %s: rijetka i kolekcionarska izdanja.',
                'plakati' => 'Originalni, stari i kolekcionarski plakati iz kategorije %s.',
                'razglednice' => 'Stare i kolekcionarske razglednice iz kategorije %s.',
                'zemljopisne-karte' => 'Stare i kolekcionarske zemljopisne karte iz kategorije %s.',
                'stari-dokumenti' => 'Stari i kolekcionarski dokumenti iz kategorije %s.',
                'dionice' => 'Povijesne i kolekcionarske dionice iz kategorije %s.',
                'diplome' => 'Stare i kolekcionarske diplome iz kategorije %s.',
                'reklame' => 'Stare reklame i promotivni materijali iz kategorije %s.',
                'ambalaza' => 'Stara i kolekcionarska ambalaža iz kategorije %s.',
            ];
            $template = $templates[$category->group] ?? 'Istražite dostupne kolekcionarske artikle iz kategorije %s.';
            $description = sprintf($template, $title);
        }

        return Str::limit($description, 155, '…');
    }
}
