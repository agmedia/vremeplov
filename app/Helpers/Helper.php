<?php

namespace App\Helpers;

use App\Models\Back\Catalog\Category;
use App\Models\Back\Marketing\Action;
use App\Models\Back\Settings\Settings;
use App\Models\Back\Widget\WidgetGroup;
use App\Models\Front\Blog;
use App\Models\Front\Catalog\Author;
use App\Models\Back\Marketing\Review;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\Publisher;
use Darryldecode\Cart\CartCondition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Helper
{

    /**
     * @param float $price
     * @param int   $discount
     *
     * @return float|int
     */
    public static function calculateDiscountPrice(float $price, int $discount, string $type)
    {
        if ($type == 'F') {
            return $price - $discount;
        }

        return $price - ($price * ($discount / 100));
    }


    /**
     * @param $list_price
     * @param $seling_price
     *
     * @return float|int
     */
    public static function calculateDiscount($list_price, $seling_price, string $type = 'P')
    {
        if (is_string($list_price)) {
            $list_price = str_replace('.', '', $list_price);
            $list_price = str_replace(',', '.', $list_price);
        }
        if (is_string($seling_price)) {
            $seling_price = str_replace('.', '', $seling_price);
            $seling_price = str_replace(',', '.', $seling_price);
        }

        if ($type == 'F') {
            return $list_price - $seling_price;
        }

        return (($list_price - $seling_price) / $list_price) * 100;
    }


    /**
     * @return string[]
     */
    public static function abc()
    {
        return [
            'A', 'B', 'C', 'Č', 'Ć', 'D', 'Dž', 'Đ', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'Lj',
            'M', 'N', 'Nj', 'O', 'P', 'R', 'S', 'Š', 'T', 'U', 'V', 'Z', 'Ž',
            // Strana slova koja se pojavljuju u imenima autora i nakladnika.
            'Q', 'W', 'X', 'Y',
        ];
    }


    /**
     * @param string $price
     *
     * @return string
     */
    public static function priceString($price): string
    {
        if (is_float($price)) {
            $price = '"' . number_format($price, 2) . '"';
        }

        if ( ! is_string($price)) {
            return 'Not a number.!';
        }

        $set = explode('.', $price);

        if ( ! isset($set[1])) {
            $set[1] = '00';
        }

        return number_format($price, 0, '', '.') . ',<small>' . substr($set[1], 0, 2) . 'kn</small>';
    }


    /**
     * @param string $target
     * @param bool   $builder
     *
     * @return array|false|Collection
     */
    public static function search(string $target = '', bool $builder = false)
    {
        if ($target != '') {
            $response = collect();

            $products = Product::query()->where('name', 'like', '%' . $target . '%')
                               ->orWhere('meta_description', 'like', '%' . $target . '%')
                               ->orWhere('sku', 'like', '%' . $target . '%')
                               ->pluck('id');

            if ( ! $products->count()) {
                $products = collect();
            }

            $preg = explode(' ', $target, 3);

            if (isset ($preg[1]) && in_array($preg[1], $preg) && ! isset($preg[2])) {
                $authors = Author::active()->where('title', 'like', '%' . $preg[0] . '%' . $preg[1] . '%')
                                 ->orWhere('title', 'like', '%' . $preg[1] . '% ' . $preg[0] . '%')
                                 ->with('products')->get();

            } elseif (isset ($preg[2]) && in_array($preg[2], $preg)) {
                $authors = Author::active()->where('title', 'like', $preg[0] . '%' . $preg[1] . '%' . $preg[2] . '%')
                                 ->orWhere('title', 'like', $preg[2] . '%' . $preg[1] . '% ' . $preg[0] . '%')
                                 ->orWhere('title', 'like', $preg[0] . '%' . $preg[2] . '% ' . $preg[1] . '%')
                                 ->orWhere('title', 'like', $preg[1] . '%' . $preg[0] . '% ' . $preg[2] . '%')
                                 ->orWhere('title', 'like', $preg[1] . '%' . $preg[2] . '% ' . $preg[0] . '%')
                                 ->with('products')->get();

            } else {
                $authors = Author::active()->where('title', 'like', '%' . $preg[0] . '%')
                                 ->with('products')->get();
            }

            foreach ($authors as $author) {
                $products = $products->merge($author->products->pluck('id'));
            }

            $response->put('products', $products->unique()->flatten());

            if ($builder) {
                return $response;
            }

            return $response['products']->toJson();
        }

        return false;
    }


    /**
     * @param Builder $query
     * @param string  $search
     *
     * @return Builder
     */
    public static function searchByTitle(Builder $query, string $search): Builder
    {
        $preg = explode(' ', $search, 3);

        if (isset ($preg[1]) && in_array($preg[1], $preg) && ! isset($preg[2])) {
            $query->where('title', 'like', '%' . $preg[0] . '%' . $preg[1] . '%')
                  ->orWhere('title', 'like', '%' . $preg[1] . '% ' . $preg[0] . '%');

        } elseif (isset ($preg[2]) && in_array($preg[2], $preg)) {
            $query->where('title', 'like', $preg[0] . '%' . $preg[1] . '%' . $preg[2] . '%')
                  ->orWhere('title', 'like', $preg[2] . '%' . $preg[1] . '% ' . $preg[0] . '%')
                  ->orWhere('title', 'like', $preg[0] . '%' . $preg[2] . '% ' . $preg[1] . '%')
                  ->orWhere('title', 'like', $preg[1] . '%' . $preg[0] . '% ' . $preg[2] . '%')
                  ->orWhere('title', 'like', $preg[1] . '%' . $preg[2] . '% ' . $preg[0] . '%');

        } else {
            $query->where('title', 'like', '%' . $preg[0] . '%');
        }

        return $query;
    }


    /**
     * @param $cat
     * @param $subcat
     *
     * @return mixed
     */
    public static function getRelated($group, $cat = null, $subcat = null)
    {
        $related = null;

        if ($subcat) {
            $related = $subcat->products()->cardData()->inRandomOrder()->take(10)->get();

        } else {
            if ($cat) {
                $related = $cat->products()->cardData()->inRandomOrder()->take(10)->get();
            }
        }

        if ( ! $related) {
            $related = Product::query()->cardData()->where('group', $group)->inRandomOrder()->take(10)->get();
        }

        if ($related->count() < 9) {
            $related = $related
                ->merge(Product::query()->cardData()->inRandomOrder()->take(10 - $related->count())->get())
                ->unique('id')
                ->values();
        }

        return $related;
    }


    /**
     * @param string $description
     *
     * @return false|string
     */
    public static function setDescription(string $description, ?int $productLimit = null)
    {
        if ($description == '') {
            return '';
        }

        // CKEditor wraps standalone widget tokens in paragraphs. Strip only
        // those wrappers so the generated <section> elements remain valid HTML.
        $description = preg_replace(
            '~<p\b[^>]*>(?:\s|&nbsp;|<br\s*/?>)*(\+\+[a-z0-9_-]+\+\+)(?:\s|&nbsp;|<br\s*/?>)*</p>~i',
            '$1',
            $description
        ) ?? $description;

        $cache_key = md5($description);

        $ids = Cache::remember('wg_ids.' . $cache_key, config('cache.life'), function () use ($description) {
            $iterator = substr_count($description, '++');
            $offset   = 0;
            $ids      = [];

            for ($i = 0; $i < $iterator / 2; $i++) {
                $from  = strpos($description, '++', $offset) + 2;
                $to    = strpos($description, '++', $from + 2);
                $ids[] = substr($description, $from, $to - $from);

                $offset = $to + 2;
            }

            return $ids;
        });

        if (empty($ids)) {
            return $description;
        }

        $wgs = Cache::remember('wgs.' . md5(implode('|', $ids)), config('cache.life'), function () use ($ids) {
            return WidgetGroup::where('status', 1)
                              ->where(function ($query) use ($ids) {
                                  $query->whereIn('id', $ids)->orWhereIn('slug', $ids);
                              })
                              ->with('widgets')
                              ->get();
        });

        foreach ($ids as $id) {
            $description = static::resolveDescription($wgs, $description, $id, $productLimit);
        }

        return $description;
    }


    /**
     * @param Collection $wgs
     * @param string     $description
     * @param string     $id
     *
     * @return string
     */
    private static function resolveDescription(Collection $wgs, string $description, string $id, ?int $productLimit = null): string
    {
        $wg = $wgs->where('id', $id)->first();

        if ( ! $wg) {
            $wg = $wgs->where('slug', $id)->first();
        }

        if ( ! $wg) {
            return str_replace('++' . $id . '++', '', $description);
        }

        $widgets = [];
        $loadedWidgets = $wg->relationLoaded('widgets')
            ? $wg->widgets->sortBy('sort_order')
            : $wg->widgets()->orderBy('sort_order')->get();

        if ($wg->template == 'product_carousel' || $wg->template == 'page_carousel') {
            $widget = $loadedWidgets->first();

            if ( ! $widget) {
                return str_replace('++' . $id . '++', '', $description);
            }

            $data = static::decodeWidgetData($widget->data);
            $items = collect();
            $tablename = '';

            if (static::isDescriptionTarget($data, 'product')) {
                $items     = static::productWidgetItems(static::products($data), $productLimit);
                $tablename = 'product';
            }

            if (static::isDescriptionTarget($data, 'blog')) {
                $items     = static::blogs($data)->get();
                $tablename = 'blog';
            }

            if (static::isDescriptionTarget($data, 'category')) {
                if ($wg->template === 'product_carousel') {
                    $items = static::productWidgetItems(static::productsByCategory($data), $productLimit);
                    $tablename = 'product_category';
                } else {
                    $items = static::category($data)->get();
                    $tablename = 'category';
                }
            }

            if (static::isDescriptionTarget($data, 'product_category')) {
                $items = static::productWidgetItems(static::productsByCategory($data), $productLimit);
                $tablename = 'product_category';
            }

            if (static::isDescriptionTarget($data, 'publisher')) {
                if ($wg->template === 'product_carousel') {
                    $items = static::productWidgetItems(static::productsByPublisher($data), $productLimit);
                    $tablename = 'publisher';
                } else {
                    $items = static::publisher($data)->get();
                    $tablename = 'publisher_list';
                }
            }

            if (static::isDescriptionTarget($data, 'author')) {
                $items = static::authors($data)->get();
                $tablename = 'author';
            }

            if (static::isDescriptionTarget($data, 'reviews')) {
                $items     = static::reviews($data)->get();
                $tablename = 'reviews';
            }

            $widgets = [
                'title'      => $widget->title,
                'subtitle'   => $widget->subtitle,
                'url'        => $widget->url,
                'tablename'  => $tablename,
                'css'        => $data['css'] ?? null,
                'container'  => (isset($data['container']) && $data['container'] == 'on') ? 1 : null,
                'background' => (isset($data['background']) && $data['background'] == 'on') ? 1 : null,
                'items'      => $items
            ];

        } else {
            foreach ($loadedWidgets as $widget) {
                $data = static::decodeWidgetData($widget->data);
                $imagePath = ltrim((string) $widget->image, '/');
                $benefitIcons = ['clock', 'box', 'location-dot', 'thumbs-up', 'truck'];
                $benefits = [];

                for ($benefitNumber = 1; $benefitNumber <= 3; $benefitNumber++) {
                    $text = trim((string) ($data['benefit_' . $benefitNumber . '_text'] ?? ''));

                    if ($text === '') {
                        continue;
                    }

                    $icon = (string) ($data['benefit_' . $benefitNumber . '_icon'] ?? 'clock');

                    $benefits[] = [
                        'text' => $text,
                        'icon' => in_array($icon, $benefitIcons, true) ? $icon : 'clock',
                    ];
                }

                $widgets[] = [
                    'id'       => $widget->id,
                    'title'    => $widget->title,
                    'subtitle' => $widget->subtitle,
                    'eyebrow'  => trim((string) ($data['eyebrow'] ?? '')),
                    'eyebrow_icon' => in_array(($data['eyebrow_icon'] ?? ''), $benefitIcons, true)
                        ? $data['eyebrow_icon']
                        : 'truck',
                    'benefits' => $benefits,
                    'color'    => $widget->badge,
                    'url'      => $widget->url,
                    'image'    => $imagePath
                        ? (is_file(public_path($imagePath))
                            ? asset($imagePath)
                            : config('settings.images_domain') . $imagePath)
                        : null,
                    'button_text' => $data['button_text'] ?? 'Pogledajte ponudu',
                    'width'    => $widget->width,
                    'right'    => (isset($data['right']) && $data['right'] == 'on') ? 1 : null,
                ];
            }
        }

        return str_replace(
            '++' . $id . '++',
            view('front.layouts.widget.widget_' . $wg->template, ['data' => $widgets]),
            $description
        );
    }


    private static function decodeWidgetData(?string $data): array
    {
        if ( ! $data) {
            return [];
        }

        $decoded = @unserialize($data, ['allowed_classes' => false]);

        return is_array($decoded) ? $decoded : [];
    }


    private static function productWidgetItems(Builder $query, ?int $limit): Collection
    {
        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        return $query->get();
    }


    /**
     * @param array  $data
     * @param string $target
     *
     * @return bool
     */
    public static function isDescriptionTarget(array $data, string $target): bool
    {
        if (isset($data['target']) && $data['target'] == $target) {
            return true;
        }
        if (isset($data['group']) && $data['group'] == $target) {
            return true;
        }
        if (isset($data['action_group']) && $data['action_group'] == $target) {
            return true;
        }

        return false;
    }


    /**
     * @param string $text
     *
     * @return string
     */
    public static function resolveFirstLetter(string $text): string
    {
        $letter = substr($text, 0, 1);

        if (in_array(substr($text, 0, 2), ['Nj', 'Lj', 'Š', 'Č', 'Ć', 'Ž', 'Đ'])) {
            $letter = substr($text, 0, 2);
        }

        if (in_array(substr($text, 0, 3), ['Dž', 'Đ'])) {
            $letter = substr($text, 0, 3);
        }

        return $letter;
    }


    /**
     * @param array $data
     *
     * @return Builder
     */
    private static function products(array $data): Builder
    {
        $prods = (new Product())->newQuery();

        $prods->active()->available();

        if ( ! empty($data['catalog_group'])) {
            $prods->where('group', $data['catalog_group']);
        }

        if (isset($data['best_selling']) && $data['best_selling'] == 'on') {
            static::applyBestSellingOrder($prods);
        } elseif (isset($data['popular']) && $data['popular'] == 'on') {
            $prods->popular();
        } elseif (isset($data['new']) && $data['new'] == 'on') {
            // "Novo" uključuje i starije naslove koji su upravo ponovno
            // stigli na zalihu, zato se vodi zadnjom izmjenom artikla.
            $prods->last(12);
        } else {
            $prods->last();
        }

        if (isset($data['list']) && $data['list']) {
            $prods->whereIn('id', $data['list']);
        }

        return $prods->cardData();
    }


    private static function productsByCategory(array $data): Builder
    {
        $products = (new Product())->newQuery()->active()->available();

        if ( ! empty($data['list'])) {
            $products->whereHas('categories', function (Builder $query) use ($data) {
                $query->whereIn('categories.id', $data['list']);
            });
        }

        static::applyProductWidgetOrder($products, $data);

        return $products->cardData()->limit(15);
    }


    private static function productsByPublisher(array $data): Builder
    {
        $products = (new Product())->newQuery()->active()->available();

        if ( ! empty($data['list'])) {
            $products->whereIn('publisher_id', $data['list']);
        }

        static::applyProductWidgetOrder($products, $data);

        return $products->with('publisher')->cardData()->limit(15);
    }


    private static function applyProductWidgetOrder(Builder $products, array $data): void
    {
        if (isset($data['best_selling']) && $data['best_selling'] == 'on') {
            static::applyBestSellingOrder($products);
        } elseif (isset($data['popular']) && $data['popular'] == 'on') {
            $products->orderByDesc('viewed')->orderByDesc('id');
        } else {
            $products->orderByDesc(isset($data['new']) ? 'created_at' : 'updated_at');
        }
    }


    private static function applyBestSellingOrder(Builder $products): void
    {
        $sales = DB::table('order_products')
            ->join('orders', 'orders.id', '=', 'order_products.order_id')
            ->whereIn('orders.order_status_id', OrderHelper::turnoverStatuses())
            ->where('orders.created_at', '>=', now()->subDays(30))
            ->groupBy('order_products.product_id')
            ->select('order_products.product_id', DB::raw('SUM(order_products.quantity) as sold_quantity'));

        $products
            ->leftJoinSub($sales, 'widget_sales', function ($join) {
                $join->on('products.id', '=', 'widget_sales.product_id');
            })
            ->select('products.*')
            ->orderByDesc('widget_sales.sold_quantity')
            ->orderByDesc('products.updated_at')
            ->limit(15);
    }


    /**
     * @param array $data
     *
     * @return Builder
     */
    private static function blogs(array $data): Builder
    {
        $blogs = (new Blog())->newQuery();

        $blogs->active();

        if (isset($data['new']) && $data['new'] == 'on') {
            // Automatski način uvijek prikazuje najnovije objave, ne ručni
            // izbor koji je možda ostao spremljen od ranije.
            return $blogs->latest('created_at')->limit(5);
        }

        if (isset($data['popular']) && $data['popular'] == 'on') {
            $blogs->popular();
        }

        if (isset($data['list']) && $data['list']) {
            $blogs->whereIn('id', $data['list']);
        }

        return $blogs;
    }


    /**
     * @param array $data
     *
     * @return Builder
     */
    private static function category(array $data): Builder
    {
        $category = (new Category())->newQuery();

        $category->active();

        if (isset($data['new']) && $data['new'] == 'on') {
            $category->latest();
        }

        if (isset($data['popular']) && $data['popular'] == 'on') {
            $category->latest();
        }

        if (isset($data['list']) && $data['list']) {
            $category->whereIn('id', $data['list']);
        }

        return $category;
    }


    /**
     * @param array $data
     *
     * @return Builder
     */
    private static function publisher(array $data): Builder
    {
        $publisher = (new Publisher())->newQuery();

        $publisher->active();

        if (isset($data['new']) && $data['new'] == 'on') {
            $publisher->latest();
        }

        if (isset($data['popular']) && $data['popular'] == 'on') {
            $publisher->latest();
        }

        if (isset($data['list']) && $data['list']) {
            $publisher->whereIn('id', $data['list']);
        }

        return $publisher;
    }


    private static function authors(array $data): Builder
    {
        $authors = (new Author())->newQuery()->active();

        if ( ! empty($data['list'])) {
            $authors->whereIn('id', $data['list']);
        }

        return $authors->orderBy('title');
    }


    /**
     * @param array $data
     *
     * @return Builder
     */
    private static function reviews(array $data): Builder
    {
        $reviews = (new Review())->newQuery()
            ->where('status', 1)
            ->with(['product.author']);

        if (isset($data['featured_only']) && $data['featured_only'] == 'on') {
            $reviews->where('featured', 1);
        }

        if ( ! empty($data['list'])) {
            $reviews->whereIn('id', $data['list']);
        }

        return $reviews
            ->orderByDesc('featured')
            ->orderBy('sort_order')
            ->latest('id')
            ->limit(15);
    }


    /**
     * @param string $tag
     *
     * @return \Illuminate\Cache\TaggedCache|mixed|object
     */
    public static function resolveCache(string $tag): ?object
    {
        if (env('APP_ENV') == 'local') {
            return Cache::getFacadeRoot();
        }

        return Cache::tags([$tag]);
    }


    /**
     * @param string $tag
     * @param string $key
     *
     * @return object|bool|mixed|null
     */
    public static function flushCache(string $tag, string $key)
    {
        if (env('APP_ENV') == 'local') {
            return Cache::getFacadeRoot();
        }

        return Cache::tags([$tag])->forget($key);
    }


    /**
     * @return null
     */
    public static function getEur()
    {
        $eur = Settings::get('currency', 'list')->where('code', 'EUR')->first();

        if (isset($eur->status) && $eur->status) {
            return $eur->value;
        }

        return null;
    }


    /**
     * @param bool $slug
     *
     * @return string
     */
    public static function categoryGroupPath(bool $slug = false): string
    {
        if ($slug) {
            return Str::slug(config('settings.group_path'));
        }

        return config('settings.group_path');
    }


    /**
     * @param array  $data
     * @param string $tag
     * @param        $target
     *
     * @return string
     */
    public static function resolveSlug(array $data, string $tag = 'title', $target = null): string
    {
        $slug = null;

        if ($target) {
            $product = Product::where('id', $target)->first();

            if ($product) {
                $slug = $product->slug;
            }
        }

        $slug  = $slug ?: Str::slug($data[$tag]);
        $exist = Product::where('slug', $slug)->count();

        $cat_exist = Category::where('slug', $slug)->count();

        if (($cat_exist || $exist > 1) && $target) {
            return $slug . '-' . time();
        }

        if (($cat_exist || $exist) && ! $target) {
            return $slug . '-' . time();
        }

        return $slug;
    }


    /**
     * @param $cart
     *
     * @return CartCondition|false
     * @throws \Darryldecode\Cart\Exceptions\InvalidConditionException
     */
    public static function hasSpecialCartCondition($cart = null)
    {
        $condition     = false;
        $has_condition = false;

        if ($cart->getTotal() > 50) {
            $has_condition = 10;
        }
        if ($cart->getTotal() > 100) {
            $has_condition = 15;
        }
        if ($cart->getTotal() > 200) {
            $has_condition = 20;
        }

        if ($has_condition && self::isDateBetween()) {
            $value    = self::calculateDiscountPrice($cart->getTotal(), $has_condition, 'P');
            $discount = $cart->getTotal() - $value;

            $condition = new CartCondition(array(
                'name'       => config('settings.special_action.title'),
                'type'       => 'special',
                'target'     => 'total', // this condition will be applied to cart's subtotal when getSubTotal() is called.
                'value'      => '-' . $discount,
                'attributes' => [
                    'description' => '',
                    'geo_zone'    => ''
                ]
            ));
        }

        return $condition;
    }


    /**
     * @param        $cart
     * @param string $coupon
     *
     * @return CartCondition|false
     * @throws \Darryldecode\Cart\Exceptions\InvalidConditionException
     */
    public static function hasCouponCartConditions($cart, string $coupon = '')
    {
        $condition = false;
        $actions   = Action::query()->where('group', 'total')->get();

        if ($actions->count()) {
            foreach ($actions as $action) {
                if ($action->isValid($coupon)) {
                    $value    = self::calculateDiscountPrice($cart->getTotal(), $action->discount, $action->type);
                    $discount = $cart->getTotal() - $value;

                    $condition = new CartCondition(array(
                        'name'       => $action->title,
                        'type'       => 'special',
                        'target'     => 'total', // this condition will be applied to cart's subtotal when getSubTotal() is called.
                        'value'      => '-' . $discount,
                        'attributes' => $action->setConditionAttributes($coupon)
                    ));
                }
            }
        }

        return $condition;
    }


    /**
     * @param $cart
     *
     * @return false|mixed
     */
    public static function isCouponUsed($cart)
    {
        $coupon = false;
        $items = $cart->getContent();

        foreach ($items as $item) {
            if ($item->getConditions()->getType() == 'coupon') {
                $coupon = $item->getConditions()->getTarget();
            }
        }

        foreach ($cart->getConditions() as $condition) {
            if (isset($condition->getAttributes()['type']) && $condition->getAttributes()['type'] == 'coupon' && floatval($condition->getValue()) < 0) {
                $coupon = $condition->getAttributes()['description'];
            }
        }



        return $coupon;
    }


    /**
     * @param $date
     *
     * @return bool
     */
    public static function isDateBetween($date = null): bool
    {
        $now   = $date ?: Carbon::now();
        $start = Carbon::createFromFormat('d/m/Y H:i:s', config('settings.special_action.start'));
        $end   = Carbon::createFromFormat('d/m/Y H:i:s', config('settings.special_action.end'));

        if ($now->isBetween($start, $end)) {
            return true;
        }

        return false;
    }


    /**
     * @param Request $request
     *
     * @return int|string
     */
    public static function resolveLetter(Request $request)
    {
        if ($request->has('letter')) {
            $requestedLetter = trim((string) $request->input('letter'));

            foreach (self::abc() as $letter) {
                if (mb_strtolower($letter, 'UTF-8') === mb_strtolower($requestedLetter, 'UTF-8')) {
                    return $letter;
                }
            }
        }

        return 0;
    }

}
