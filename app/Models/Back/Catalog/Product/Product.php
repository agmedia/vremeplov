<?php

namespace App\Models\Back\Catalog\Product;

use App\Helpers\Helper;
use App\Helpers\ProductHelper;
use App\Models\Back\Catalog\Author;
use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Settings\Settings;
use App\Support\CatalogFilterValue;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Bouncer;
use Illuminate\Validation\ValidationException;

class Product extends Model
{

    use HasFactory;

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
    protected $appends = ['thumb'];

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var null
     */
    protected $old_product = null;

    /** Attributes that are controlled enumerations in the manual admin form. */
    private const CONTROLLED_ATTRIBUTES = ['letter', 'condition', 'binding', 'origin'];


    /**
     * @return array|mixed|string|string[]
     */
    public function getThumbAttribute()
    {
        $path = $this->image ? str_replace('.jpg', '-thumb.webp', $this->image) : 'media/avatars/avatar0.jpg';

        return rtrim((string) config('settings.images_domain'), '/') . '/' . ltrim($path, '/');
    }


    /**
     * Return the original catalog image used by the admin lightbox.
     */
    public function getImageUrlAttribute(): string
    {
        $path = trim((string) $this->image);

        if ($path === '') {
            $path = 'media/avatars/avatar0.jpg';
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        $domain = rtrim((string) (config('settings.images_domain') ?: config('app.url')), '/');

        return $domain . '/' . ltrim($path, '/');
    }


    /**
     * @return Relation
     */
    public function categories()
    {
        return $this->hasManyThrough(Category::class, ProductCategory::class, 'product_id', 'id', 'id', 'category_id')->where('parent_id', '==', 0);
    }


    /**
     * @return Relation
     */
    public function subcategories()
    {
        return $this->hasManyThrough(Category::class, ProductCategory::class, 'product_id', 'id', 'id', 'category_id')->where('parent_id', '!=', 0);
    }


    /**
     * @return Model|\Illuminate\Database\Eloquent\Relations\HasOneThrough|\Illuminate\Database\Query\Builder|mixed|object|null
     */
    public function category()
    {
        return $this->hasOneThrough(Category::class, ProductCategory::class, 'product_id', 'id', 'id', 'category_id')
                    ->where('parent_id', '=', 0)
                    ->first();
    }


    /**
     * @return Model|\Illuminate\Database\Eloquent\Relations\HasOneThrough|\Illuminate\Database\Query\Builder|mixed|object|null
     */
    public function subcategory()
    {
        return $this->hasOneThrough(Category::class, ProductCategory::class, 'product_id', 'id', 'id', 'category_id')
                    ->where('parent_id', '!=', 0)
                    ->first();
    }


    /**
     * @return Relation
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('sort_order');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function author()
    {
        return $this->belongsTo(Author::class, 'author_id');
    }


    /**
     * @return Relation
     */
    public function all_actions()
    {
        return $this->hasOne(ProductAction::class, 'product_id');
    }


    /**
     * @return false|mixed
     */
    public function special()
    {
        // If special is set, return special.
        if ($this->special) {
            $from = now()->subDay();
            $to   = now()->addDay();

            if ($this->special_from) {
                $from = Carbon::make($this->special_from);
            }
            if ($this->special_to) {
                $to = Carbon::make($this->special_to);
            }

            if ($from <= now() && now() <= $to) {
                return $this->special;
            }
        }

        return false;
    }


    public function imageName()
    {
        $from   = strrpos($this->image, '/') + 1;
        $length = strrpos($this->image, '-') - $from;

        return substr($this->image, $from, $length);
    }


    /**
     * Validate New Product Request.
     *
     * @param Request $request
     *
     * @return $this
     */
    public function validateRequest(Request $request)
    {
        $attributeRules = [];

        foreach (self::CONTROLLED_ATTRIBUTES as $attribute) {
            $input = $request->input($attribute);

            if ($input === null || is_scalar($input)) {
                $normalized = CatalogFilterValue::storageDisplay($attribute, $input);
                $request->merge([
                    $attribute => $normalized === '' ? null : $normalized,
                ]);
            }

            $currentValue = $this->exists ? $this->getOriginal($attribute) : null;
            $currentData = $this->controlledAttributeData($attribute, $currentValue);
            $attributeRules[$attribute] = [
                'nullable',
                'string',
                'max:191',
                Rule::in($currentData['options']),
            ];
        }

        if (is_string($request->input('note'))) {
            $note = trim($request->input('note'));
            $request->merge(['note' => $note === '' ? null : $note]);
        }

        // Validate the request.
        $request->validate(array_merge([
            'name'     => 'required',
            'sku'      => 'required',
            'price'    => 'required',
            'quantity' => 'required',
            'group'    => 'required',
            'note'     => 'nullable|string|max:2000',
        ], $attributeRules), [
            'letter.in' => 'Odaberite pismo s ponuđenog popisa.',
            'condition.in' => 'Odaberite stanje s ponuđenog popisa.',
            'binding.in' => 'Odaberite uvez s ponuđenog popisa.',
            'origin.in' => 'Odaberite jezik s ponuđenog popisa.',
        ]);

        // Set Product Model request variable
        $this->setRequest($request);

        if ($this->isDuplicateSku()) {
            throw ValidationException::withMessages(['sku_dupl' => $this->request->sku . ' - Šifra već postoji...']);
        }

        return $this;
    }


    /**
     * Create and return new Product Model.
     *
     * @return mixed
     */
    public function create()
    {
        $slug  = $this->resolveSlug();
        $group = isset($this->request->group) ? $this->request->group : null;

        $id = $this->insertGetId([
            'author_id'            => $this->request->author_id ?: 6,
            'publisher_id'         => $this->request->publisher_id ?: 2,
            'action_id'            => $this->request->action ?: 0,
            'name'                 => $this->request->name,
            'sku'                  => $this->request->sku,
            'polica'               => $this->request->polica,
            //'isbn'                 => $this->request->isbn,
            'description'          => $this->cleanHTML($this->request->description),
            'slug'                 => $slug,
            'group'                => $group,
            'price'                => $this->request->price,
            'quantity'             => $this->request->quantity ?: 0,
            'decrease'             => 1,
            'tax_id'               => $this->request->tax_id ?: 1,
            'special'              => $this->request->special,
            'special_from'         => $this->request->special_from ? Carbon::make($this->request->special_from) : null,
            'special_to'           => $this->request->special_to ? Carbon::make($this->request->special_to) : null,
            'meta_title'           => $this->request->meta_title ?: $this->request->name/* . ($author ? '-' . $author->title : '')*/,
            'meta_description'     => $this->request->meta_description,
            'pages'                => $this->request->pages,
            'dimensions'           => $this->request->dimensions,
            'origin'               => $this->request->origin,
            'letter'               => $this->request->letter,
            'condition'            => $this->request->condition,
            'binding'              => $this->request->binding,
            'note'                 => $this->request->note,
            'year'                 => $this->request->year,
            //'shipping_time'        => $this->request->shipping_time,
            /*'youtube_product_url'  => $this->request->youtube_product_url,
            'youtube_channel'      => $this->request->youtube_channel,
            'goodreads_author_url' => $this->request->goodreads_author_url,
            'goodreads_book_url'   => $this->request->goodreads_book_url,
            'author_web_url'       => $this->request->author_web_url,
            'serial_web_url'       => $this->request->serial_web_url,
            'wiki_url'             => $this->request->wiki_url,*/
            'viewed'               => 0,
            'sort_order'           => 0,
            'push'                 => 0,
            'status'               => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
            'created_at'           => Carbon::now(),
            'updated_at'           => Carbon::now()
        ]);

        if ($id) {
            $this->resolveCategories($id);

            $product = $this->find($id);

            $product->update([
                'group' => ProductHelper::group($product, $group)
            ]);

            $product->update([
                'url'             => ProductHelper::url($product),
                'category_string' => ProductHelper::categoryString($product)
            ]);

            return $product;
        }

        return false;
    }


    /**
     * Update and return new Product Model.
     *
     * @return mixed
     */
    public function edit()
    {
        $this->old_product = $this->setHistoryProduct();

        $slug  = $this->request->slug;//$this->resolveSlug('update');
        $group = isset($this->request->group) ? $this->request->group : null;

        $updated = $this->update([
            'author_id'            => $this->request->author_id ?: 6,
            'publisher_id'         => $this->request->publisher_id ?: 2,
            'action_id'            => $this->request->action ?: 0,
            'name'                 => $this->request->name,
            'sku'                  => $this->request->sku,
            'polica'               => $this->request->polica,
            'isbn'                 => $this->request->isbn,
            'description'          => $this->cleanHTML($this->request->description),
            'slug'                 => $slug,
            'group'                => $group,
            'price'                => isset($this->request->price) ? $this->request->price : 0,
            'quantity'             => $this->request->quantity ?: 0,
            'decrease'             => 1,
            'tax_id'               => $this->request->tax_id ?: 1,
            'special'              => $this->request->special,
            'special_from'         => $this->request->special_from ? Carbon::make($this->request->special_from) : null,
            'special_to'           => $this->request->special_to ? Carbon::make($this->request->special_to) : null,
            'meta_title'           => $this->request->meta_title ?: $this->request->name/* . '-' . ($author ? '-' . $author->title : '')*/,
            'meta_description'     => $this->request->meta_description,
            'pages'                => $this->request->pages,
            'dimensions'           => $this->request->dimensions,
            'origin'               => $this->request->origin,
            'letter'               => $this->request->letter,
            'condition'            => $this->request->condition,
            'binding'              => $this->request->binding,
            'note'                 => $this->request->note,
            'year'                 => $this->request->year,
            'shipping_time'        => $this->request->shipping_time,
            'youtube_product_url'  => $this->request->youtube_product_url,
            'youtube_channel'      => $this->request->youtube_channel,
            'goodreads_author_url' => $this->request->goodreads_author_url,
            'goodreads_book_url'   => $this->request->goodreads_book_url,
            'author_web_url'       => $this->request->author_web_url,
            'serial_web_url'       => $this->request->serial_web_url,
            'wiki_url'             => $this->request->wiki_url,
            'viewed'               => 0,
            'sort_order'           => 0,
            'push'                 => 0,
            'status'               => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
            'updated_at'           => Carbon::now()
        ]);

        if ($updated) {
            $this->resolveCategories($this->id);

            $this->update([
                'group' => ProductHelper::group($this, $group)
            ]);

            $this->update([
                'url'             => ProductHelper::url($this),
                'category_string' => ProductHelper::categoryString($this)
            ]);

            return $this;
        }

        return false;
    }


    /**
     * @return array
     */
    public function getRelationsData(): array
    {
        $attributeValues = [];
        $legacyAttributeValues = [];

        $attributeOptions = [];

        foreach (self::CONTROLLED_ATTRIBUTES as $attribute) {
            $currentValue = $this->exists ? $this->getAttribute($attribute) : null;
            $currentData = $this->controlledAttributeData($attribute, $currentValue);
            $attributeOptions[$attribute] = $currentData['options'];
            $attributeValues[$attribute] = $currentData['selected'];
            $legacyAttributeValues[$attribute] = $currentData['legacy'];
        }

        return [
            'categories'     => (new Category())->getList(false),
            'groups'         => \App\Models\Front\Catalog\Category::getGroups(),
            'images'         => ProductImage::getAdminList($this->id),
            'letters'        => $attributeOptions['letter'],
            'conditions'     => $attributeOptions['condition'],
            'bindings'       => $attributeOptions['binding'],
            'origins'        => $attributeOptions['origin'],
            'attribute_values' => $attributeValues,
            'legacy_attribute_values' => $legacyAttributeValues,
            'shipping_times' => Settings::get('product', 'shipping_time_styles'),
            'taxes'          => Settings::get('tax', 'list')
        ];
    }


    /**
     * Keep an unsupported legacy value selectable only on its existing
     * product, while all new values must come from the shared facet options.
     */
    private function controlledAttributeData(string $attribute, $currentValue): array
    {
        $selected = CatalogFilterValue::storageDisplay($attribute, $currentValue);
        $selected = $selected === '' ? null : $selected;
        $options = CatalogFilterValue::facetOptions($attribute);
        $legacy = null;

        if ($selected !== null && ! in_array($selected, $options, true)) {
            $legacy = $selected;
            $options[] = $legacy;
        }

        return [
            'options' => array_values(array_unique($options)),
            'selected' => $selected,
            'legacy' => $legacy,
        ];
    }


    /**
     * @return $this
     */
    public function checkSettings()
    {
        Settings::setProduct('shipping_time_styles', $this->request->shipping_time);

        return $this;
    }


    /**
     * @param Product $product
     *
     * @return mixed
     */
    public function storeImages(Product $product)
    {
        return (new ProductImage())->store($product, $this->request);
    }


    /**
     * @param string $type
     *
     * @return mixed
     */
    public function addHistoryData(string $type)
    {
        $new = $this->setHistoryProduct();

        $history = new ProductHistory($new, $this->old_product);

        return $history->addData($type);
    }


    /**
     * @param Request $request
     *
     * @return Builder
     */
    public function filter(Request $request): Builder
    {
        $query = (new Product())->newQuery();

        if ($request->has('search') && ! empty($request->input('search'))) {
            $query->where('name', 'like', '%' . $request->input('search') . '%')
                  ->orWhere('sku', 'like', '%' . $request->input('search') . '%')
                  ->orWhere('polica', 'like', '%' . $request->input('search') . '%')
                  ->orWhere('year', 'like', '' . $request->input('search') . '');
        }

        if ($request->has('category') && ! empty($request->input('category'))) {
            $query->whereHas('categories', function ($query) use ($request) {
                $query->where('id', $request->input('category'));
            })->orWhereHas('subcategories', function ($query) use ($request) {
                $query->where('id', $request->input('category'));
            });
        }

        if ($request->has('author') && ! empty($request->input('author'))) {
            $query->where('author_id', $request->input('author'));
        }

        if ($request->has('publisher') && ! empty($request->input('publisher'))) {
            $query->where('publisher_id', $request->input('publisher'));
        }

        if ($request->has('status')) {
            if ($request->input('status') == 'active') {
                $query->where('status', 1);
            }
            if ($request->input('status') == 'inactive') {
                $query->where('status', 0);
            }
        }

        if ($request->has('status')) {
            if ($request->input('status') == 'kolicina') {
                $query->where('quantity', 0);
            }

        }

        if ($request->has('sort')) {
            if ($request->input('sort') == 'new') {
                $query->orderBy('created_at', 'desc');
            }
            if ($request->input('sort') == 'old') {
                $query->orderBy('created_at', 'asc');
            }
            if ($request->input('sort') == 'price_up') {
                $query->orderBy('price', 'asc');
            }
            if ($request->input('sort') == 'price_down') {
                $query->orderBy('price', 'desc');
            }
            if ($request->input('sort') == 'az') {
                $query->orderBy('name', 'asc');
            }
            if ($request->input('sort') == 'za') {
                $query->orderBy('name', 'desc');
            }
            if ($request->input('sort') == 'qty_up') {
                $query->orderBy('quantity', 'asc');
            }
            if ($request->input('sort') == 'qty_down') {
                $query->orderBy('quantity', 'desc');
            }
        } else {
            $query->orderBy('updated_at', 'desc');

        }

        return $query;
    }


    /**
     * @param $request
     *
     * @return void
     */
    private function setRequest($request)
    {
        $this->request = $request;
    }


    /**
     * @return mixed
     */
    private function setHistoryProduct()
    {
        $product = $this->where('id', $this->id)->first();

        $response             = $product->toArray();
        $response['category'] = [];

        if ($product->category()) {
            $response['category'] = $product->category()->toArray();
        }

        $response['subcategory'] = $product->subcategory() ? $product->subcategory()->toArray() : [];
        $response['images']      = $product->images()->get()->toArray();

        return $response;
    }


    /**
     * @param null $description
     *
     * @return string
     */
    private function cleanHTML($description = null): string
    {
        $clean = preg_replace('/ style=("|\')(.*?)("|\')/', '', $description ?: '');

        return preg_replace('/ face=("|\')(.*?)("|\')/', '', $clean);
    }


    /**
     * @param int $product_id
     *
     * @return bool
     */
    private function resolveCategories(int $product_id): bool
    {
        $category = [];

        if (isset($this->request->category) && ! empty($this->request->category) && is_array($this->request->category)) {
            $category = $this->request->category;
        }

        $created = ProductCategory::storeData($category, $product_id);

        if (is_array($created)) {
            return true;
        }

        return false;
    }


    /**
     * @param string       $target
     * @param Request|null $request
     *
     * @return string
     */
    private function resolveSlug(string $target = 'insert', Request $request = null): string
    {
        $slug = null;

        if ($request) {
            $this->request = $request;
        }

        if ($target == 'update') {
            $product = Product::where('id', $this->id)->first();

            if ($product) {
                $slug = $product->slug;
            }
        }

        $slug  = $slug ?: Str::slug($this->request->name);
        $exist = $this->where('slug', $slug)->count();

        $cat_exist = Category::where('slug', $slug)->count();

        if (($cat_exist || $exist > 1) && $target == 'update') {
            return $slug . '-' . time();
        }

        if (($cat_exist || $exist) && $target == 'insert') {
            return $slug . '-' . time();
        }

        return $slug;
    }


    /**
     * @return bool
     */
    private function isDuplicateSku(): bool
    {
        $exist = $this->where('sku', $this->request->sku)->first();

        if (isset($this->id) && $exist && $exist->id != $this->id) {
            return true;
        }

        return false;
    }

}
