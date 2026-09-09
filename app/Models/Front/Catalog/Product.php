<?php

namespace App\Models\Front\Catalog;

use App\Helpers\Currency;
use App\Models\Back\Catalog\Product\ProductAction;
use App\Models\Back\Marketing\Review;
use App\Models\Back\Settings\Settings;
use App\Support\CatalogFilterValue;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Bouncer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 *
 */
class Product extends Model
{

    /**
     * @var string
     */
    protected $table = 'products';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * @var string[]
     */
    protected $appends = [
        'eur_price',
        'eur_special',
        'main_price',
        'main_price_text',
        'main_special',
        'main_special_text',
        'secondary_price',
        'secondary_price_text',
        'secondary_special',
        'secondary_special_text',
        'card_name',
    ];

    /**
     * @var
     */
    protected $eur;


    /**
     * Calm down legacy all-caps titles on customer-facing product cards without
     * changing the stored catalog name or already well-formatted titles.
     */
    public function getCardNameAttribute(): string
    {
        $name = trim(htmlspecialchars_decode((string) $this->name, ENT_QUOTES));

        if ($name === '' || ! preg_match('/\p{L}/u', $name)) {
            return $name;
        }

        preg_match_all('/\p{L}/u', $name, $letters);
        $uppercaseLetters = 0;
        $lowercaseLetters = 0;

        foreach ($letters[0] as $letter) {
            $uppercase = mb_strtoupper($letter, 'UTF-8');
            $lowercase = mb_strtolower($letter, 'UTF-8');

            if ($uppercase === $lowercase) {
                continue;
            }

            if ($letter === $uppercase) {
                $uppercaseLetters++;
            } else {
                $lowercaseLetters++;
            }
        }

        $casedLetterCount = $uppercaseLetters + $lowercaseLetters;
        $isLegacyUppercaseTitle = $casedLetterCount >= 4
            && ($uppercaseLetters / $casedLetterCount) >= .8;

        if (! $isLegacyUppercaseTitle) {
            return $name;
        }

        $protectedTokens = [];
        $name = preg_replace_callback(
            '/\b(?:(DVD|CD|VHS|LP|EP|HNK|HRT|HAZU|JAZU|ISBN|ISSN|EAN|EU|RH|BIH|SAD|SFRJ|SSSR|NOB|NATO|UNESCO|PDF|TV|PC|AI|VBZ)(?:-(\p{L}{1,8}))?|([IVXLCDM]{2,}))\b/u',
            static function (array $match) use (&$protectedTokens): string {
                $placeholder = "\u{E000}" . count($protectedTokens) . "\u{E001}";
                $token = ! empty($match[1]) ? $match[1] : $match[3];
                $token = $token === 'BIH' ? 'BiH' : $token;

                if (! empty($match[2])) {
                    $token .= '-' . mb_strtolower($match[2], 'UTF-8');
                }

                $protectedTokens[$placeholder] = $token;

                return $placeholder;
            },
            $name
        ) ?: $name;

        $name = mb_convert_case(mb_strtolower($name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $name = preg_replace_callback(
            '/(?<=\s)(I|Te|Pa|Ni|Niti|A|Ali|Nego|No|Ili|U|Na|Za|Od|Do|Iz|S|Sa|O|Po|Pri|Prema|Kroz|Bez)(?=\s)/u',
            static fn (array $match): string => mb_strtolower($match[0], 'UTF-8'),
            $name
        );
        $name = strtr($name, $protectedTokens);

        return preg_replace('/\s+([,:;.!?])/u', '$1', $name) ?: $name;
    }


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
     * @return Collection|string
     */
    public function getMainPriceAttribute()
    {
        return Currency::main($this->price);
    }


    /**
     * @return Collection|string
     */
    public function getMainPriceTextAttribute()
    {
        return Currency::main($this->price, true);
    }


    /**
     * @return Collection|string
     */
    public function getMainSpecialAttribute()
    {
        return Currency::main($this->special());
    }


    /**
     * @return Collection|string
     */
    public function getMainSpecialTextAttribute()
    {
        return Currency::main($this->special(), true);
    }


    /**
     * @return Collection|string
     */
    public function getSecondaryPriceAttribute()
    {
        return Currency::secondary($this->price);
    }


    /**
     * @return Collection|string
     */
    public function getSecondaryPriceTextAttribute()
    {
        return Currency::secondary($this->price, true);
    }


    /**
     * @return Collection|string
     */
    public function getSecondarySpecialAttribute()
    {
        return Currency::secondary($this->special());
    }


    /**
     * @return Collection|string
     */
    public function getSecondarySpecialTextAttribute()
    {
        return Currency::secondary($this->special(), true);
    }


    /**
     * @return string
     */
    public function getEurPriceAttribute()
    {
        $this->eur = Settings::get('currency', 'list')->where('code', 'EUR')->first();

        if (isset($this->eur->status) && $this->eur->status) {
            return number_format(($this->price * $this->eur->value), 2);
        }

        return null;
    }

    /**
     * @return string
     */
    public function getEurSpecialAttribute()
    {
        $this->eur = Settings::get('currency', 'list')->where('code', 'EUR')->first();

        if (isset($this->eur->status) && $this->eur->status) {
            return number_format(($this->special() * $this->eur->value), 2);
        }

        return null;
    }


    /**
     * @param $value
     *
     * @return array|string|string[]
     */
    public function getImageAttribute($value)
    {
        return config('settings.images_domain') . str_replace('.jpg', '.webp', $value);
    }


    /**
     * @param $value
     *
     * @return array|string|string[]
     */
    public function getThumbAttribute($value)
    {
        return str_replace('.webp', '-thumb.webp', $this->image);
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id')->where('published', 1)->orderBy('sort_order');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id')->where('status', 1)->orderBy('sort_order');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function action()
    {
        return $this->hasOne(ProductAction::class, 'id', 'action_id')->where('status', 1);
    }


    /**
     * @param $ocjena
     * @param $total
     *
     * @return float|void
     */
    public function percentreviews($ocjena, $total) {
        if ($total) {
            return round(($ocjena / $total) * 100, 2);
        }

        return 0;
    }


    /**
     * @return false|float|int|mixed
     */
    public function special()
    {
        $coupon_session_key = config('session.cart') . '_coupon';
        $coupon_ok = false;

        if ( ! $this->action || ($this->action && ! $this->action->coupon)) {
            $coupon_ok = true;
        }

        if ((isset($this->action->coupon) && $this->action->coupon) && session()->has($coupon_session_key) && session($coupon_session_key) == $this->action->coupon) {
            $coupon_ok = true;
        }

        // If special is set, return special.
        if ($this->special && $coupon_ok) {
            $from = now()->subDay();
            $to = now()->addDay();

            if ($this->special_from && $this->special_from != '0000-00-00 00:00:00') {
                $from = Carbon::make($this->special_from);
            }
            if ($this->special_to && $this->special_to != '0000-00-00 00:00:00') {
                $to = Carbon::make($this->special_to);
            }

            if ($from <= now() && now() <= $to) {
                return $this->special;
            }
        }

        return $this->price;
    }


    /**
     * @return string
     */
    public function coupon(): string
    {
        $action = $this->action;
        $coupon_session_key = config('session.cart') . '_coupon';
        $coupon_ok = '';

        if ( ! $action || ($action && ! $action->coupon)) {
            $coupon_ok = '';
        }

        if ($action && $action->status) {
            if ((isset($action->coupon) && $action->coupon) && session()->has($coupon_session_key) && session($coupon_session_key) == $action->coupon) {
                $coupon_ok = true;
            }
        }

        return $coupon_ok;
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function author()
    {
        return $this->hasOne(Author::class, 'id', 'author_id');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function publisher()
    {
        return $this->hasOne(Publisher::class, 'id', 'publisher_id');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function categories()
    {
        return $this->hasManyThrough(Category::class, CategoryProducts::class, 'product_id', 'id', 'id', 'category_id');
    }


    /**
     * @return Model|\Illuminate\Database\Eloquent\Relations\HasOneThrough|\Illuminate\Database\Query\Builder|mixed|object|null
     */
    public function category()
    {
        return $this->hasOneThrough(Category::class, CategoryProducts::class, 'product_id', 'id', 'id', 'category_id')
            ->where('parent_id', 0)
            ->first();
    }


    /**
     * @return Model|\Illuminate\Database\Eloquent\Relations\HasOneThrough|\Illuminate\Database\Query\Builder|mixed|object|null
     */
    public function subcategory()
    {
        return $this->hasOneThrough(Category::class, CategoryProducts::class, 'product_id', 'id', 'id', 'category_id')
            ->where('parent_id', '!=', 0)
            ->first();
    }


    /**
     * @return string
     */
    public function priceString(?string $price = null)
    {
        if ($price) {
            $set = explode('.', $price);

            if ( ! isset($set[1])) {
                $set[1] = '00';
            }

            return number_format($price, 0, '', '.') . ',' . substr($set[1], 0, 2) . ' kn';
        }

        $set = explode('.', $this->price);

        return number_format($this->price, 0, '', '.') . ',' . substr($set[1], 0, 2) . ' kn';
    }


    /**
     * @param int $id
     *
     * @return mixed
     */
    public function tax(int $id)
    {
        return Settings::get('tax', 'list')->where('id', $id)->first();
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeOnAction($query)
    {
        $actions = ProductAction::active()->pluck('product_id');

        if ($actions->count() < 8) {
            $count = 8 - $actions->count();

            for ($i = 0; $i < $count; $i++) {
                $product = Product::whereNotIn('id', $actions)->inRandomOrder()->limit(1)->pluck('id');
                $actions->push($product[0]);
            }
        }

        return $query->whereIn('id', $actions)->with('action');
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1)->where('price', '!=', 0);
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 0);
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeHasStock(Builder $query): Builder
    {
        return $query->where('quantity', '!=', 0);
    }


    /**
     * Match a normalized filter value against all of its stored variants.
     */
    public function scopeWhereFacetValues(Builder $query, string $column, array $values): Builder
    {
        $allowedColumns = ['letter', 'condition', 'binding', 'origin'];
        if ( ! in_array($column, $allowedColumns, true)) {
            return $query->whereRaw('1 = 0');
        }

        $keys = collect($values)
            ->flatMap(fn ($value) => CatalogFilterValue::facetValues($column, $value))
            ->map(fn ($value) => CatalogFilterValue::key($value))
            ->filter()
            ->unique();

        if ($keys->isEmpty()) {
            return $query;
        }

        $storedValueIndex = Cache::remember(
            'catalog.facet-value-index:v1:' . $column,
            60,
            function () use ($column) {
                return static::query()
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->distinct()
                    ->pluck($column)
                    ->reduce(function (array $index, $storedValue) use ($column) {
                        foreach (CatalogFilterValue::facetValues($column, $storedValue) as $facetValue) {
                            $key = CatalogFilterValue::key($facetValue);

                            if ($key !== '') {
                                $index[$key][] = $storedValue;
                            }
                        }

                        return $index;
                    }, []);
            }
        );
        $storedValues = $keys
            ->flatMap(fn ($key) => $storedValueIndex[$key] ?? [])
            ->unique()
            ->values();

        return $query->whereIn($column, $storedValues->isEmpty() ? ['__no_matching_value__'] : $storedValues);
    }


    /**
     * Products with a real customer-facing discount.
     */
    public function scopeOnSale(Builder $query): Builder
    {
        $now = now();

        return $query
            ->whereNotNull('special')
            ->where('special', '>', 0)
            ->whereColumn('special', '<', 'price')
            ->where(function (Builder $query) use ($now) {
                $query->whereNull('special_from')
                    ->orWhere('special_from', '0000-00-00 00:00:00')
                    ->orWhere('special_from', '<=', $now);
            })
            ->where(function (Builder $query) use ($now) {
                $query->whereNull('special_to')
                    ->orWhere('special_to', '0000-00-00 00:00:00')
                    ->orWhere('special_to', '>=', $now);
            });
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeLast(Builder $query, $count = 12): Builder
    {
        return $query
            ->where('status', 1)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($count);
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeCreated($query, $count = 9)
    {
        return $query->where('status', 1)->orderBy('created_at', 'desc')->limit($count);
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('quantity', '!=', 0);
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopePopular(Builder $query, $count = 12): Builder
    {
        return $query->where('status', 1)->orderByDesc('viewed')->orderByDesc('id')->limit($count);
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeTopPonuda(Builder $query, $count = 12): Builder
    {
        return $query->where('status', 1)->where('topponuda', 1)->orderBy('updated_at', 'desc')->limit($count);
    }


    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeBasicData(Builder $query): Builder
    {
        return $query->select('id', 'name', 'url', 'image', 'price', 'special', 'author_id');
    }


    /**
     * Relationships and aggregates shared by every customer-facing card.
     */
    public function scopeCardData(Builder $query): Builder
    {
        return $query
            ->with(['author', 'action', 'categories'])
            ->withCount('reviews')
            ->withAvg('reviews', 'stars');
    }

    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * @param Request         $request
     * @param Collection|null $ids
     *
     * @return Builder
     */
    public function filter(Request $request, ?Collection $ids = null): Builder
    {
        $query = $this->newQuery();

        $query->active()->hasStock();

        if ($ids && $ids->count() && ! \request()->has('pojam')) {
            $query->whereIn('id', $ids->unique());
        }

        if ($request->has('ids') && $request->input('ids') != '') {
            $rawIds = $request->input('ids');

            if (is_string($rawIds)) {
                $decodedIds = json_decode($rawIds, true);
                $rawIds = is_array($decodedIds)
                    ? $decodedIds
                    : explode(',', trim($rawIds, '[]'));
            }

            $filteredIds = collect($rawIds)
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $query->whereIn('id', $filteredIds->isEmpty() ? [0] : $filteredIds);
        }

        if ($request->has('group')) {
            //Log::info($request->toArray());
            $query->where('group', $request->input('group'));
        }

        if ($request->has('cat')) {
            $query->whereHas('categories', function ($query) use ($request) {
                $query->where('category_id', $request->input('cat'));
            });
        }

        if ($request->has('subcat')) {
            $query->whereHas('categories', function ($query) use ($request) {
                $query->where('category_id', $request->input('subcat'));
            });
        }

        if ($request->has('autor')) {
            $auts = [];

            foreach ($request->input('autor') as $key => $item) {
                if (isset($item->id)) {
                    array_push($auts, $item->id);
                } else {
                    array_push($auts, $key);
                }
            }

            $query->whereIn('author_id', $auts);
        }

        if ($request->has('nakladnik')) {
            $pubs = [];

            foreach ($request->input('nakladnik') as $key => $item) {
                if (isset($item->id)) {
                    array_push($pubs, $item->id);
                } else {
                    array_push($pubs, $key);
                }
            }

            $query->whereIn('publisher_id', $pubs);
        }

        if ($request->has('start')) {
            $query->where(function ($query) use ($request) {
                $query->where('year', '>=', $request->input('start'))->orWhereNull('year');
            });
        }

        if ($request->has('end')) {
            $query->where(function ($query) use ($request) {
                $query->where('year', '<=', $request->input('end'))->orWhereNull('year');
            });
        }

        foreach ([
            'pismo' => 'letter',
            'stanje' => 'condition',
            'uvez' => 'binding',
            'jezik' => 'origin',
        ] as $parameter => $column) {
            if ($request->filled($parameter)) {
                $requestedValues = is_array($request->input($parameter))
                    ? $request->input($parameter)
                    : preg_split('/[+|]/', (string) $request->input($parameter));

                $values = collect($requestedValues)
                    ->map(fn ($value) => CatalogFilterValue::facetKey($column, $value))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($values) {
                    $query->whereFacetValues($column, $values);
                }
            }
        }

        $sort = $request->input('sort', 'novi');

        switch ($sort) {
            case 'price_up':
                $query->orderBy('price');
                break;
            case 'price_down':
                $query->orderBy('price', 'desc');
                break;
            case 'naziv_up':
                $query->orderBy('name');
                break;
            case 'naziv_down':
                $query->orderBy('name', 'desc');
                break;
            case 'novi':
            default:
                $query->orderByDesc('updated_at')->orderByDesc('id');
                break;
        }

        return $query;
    }


    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/
    // Static functions

    /**
     * @return mixed
     */
    public static function getMenu()
    {
        return self::where('status', 1)->select('id', 'name')->get();
    }


    /**
     * Return the list usually for
     * select or autocomplete html element.
     *
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public static function list()
    {
        $query = (new self())->newQuery();

        return $query->where('status', 1)->select('id', 'name')->get();
    }

}
