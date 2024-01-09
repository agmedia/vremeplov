<?php

namespace App\Models\Back\Settings\Api;

use App\Helpers\ApiHelper;
use App\Helpers\Helper;
use App\Helpers\Import;
use App\Helpers\ProductHelper;
use App\Helpers\Query;
use App\Models\Back\Catalog\Author;
use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Product\ProductCategory;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Settings\Settings;
use App\Models\Back\TempTable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class OC_Import
{

    /**
     * @var array|null
     */
    protected $request;

    /*******************************************************************************
    *                                Copyright : AGmedia                           *
    *                              email: filip@agmedia.hr                         *
    *******************************************************************************/

    public function getCategories(int $parent_id = 0)
    {
        if ($parent_id) {
            return DB::connection('oc')->table('oc_category')->where('parent_id', $parent_id)->get();
        }

        return DB::connection('oc')->table('oc_category')->where('parent_id', 0)->get();
    }


    public function getCategoryDescription(int $category_id)
    {
        return DB::connection('oc')->table('oc_category_description')->where('category_id', $category_id)->first();
    }


    public function getCategoryPath(int $category_id)
    {
        return DB::connection('oc')->table('oc_seo_url')->where('query', 'category_id=' . $category_id)->first();
    }


    /**
     * @param string $name
     * @param int    $parent
     *
     * @return mixed
     */
    public function saveCategory(
        string $name,
        string $group,
        string $slug,
        string $meta_title = '',
        string $meta_description = '',
        int $parent = 0,
        int $sort_order = 0,
        $old_category_id = 0
    )
    {
        return Category::insertGetId([
            'parent_id'        => $parent,
            'title'            => $name,
            'description'      => '',
            'meta_title'       => $meta_title,
            'meta_description' => $meta_description,
            'image'            => $old_category_id,
            'group'            => $group,
            'lang'             => 'hr',
            'sort_order'       => $sort_order,
            'status'           => 1,
            'slug'             => $slug,
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now()
        ]);
    }

    /*******************************************************************************
    *                                Copyright : AGmedia                           *
    *                              email: filip@agmedia.hr                         *
    *******************************************************************************/

    /**
     * @param int|array $offset
     * @param int       $limit
     * @param string    $order_by
     * @param string    $direction
     *
     * @return \Illuminate\Support\Collection
     */
    public function getProducts($offset = 0, int $limit = 0, string $order_by = 'product_id', string $direction = 'asc')
    {
        if (is_array($offset)) {
            return DB::connection('oc')
                     ->table('oc_product')
                     ->whereIn('product_id', $offset)
                     ->orderBy($order_by, $direction)
                     ->get();
        }

        $query = DB::connection('oc')->table('oc_product');

        if ($offset) {
            $query->offset($offset);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->orderBy($order_by, $direction)->get();
    }


    public function getProduct(int $product_id)
    {
        return DB::connection('oc')->table('oc_product')->where('product_id', $product_id)->first();
    }


    public function getProductDescription(int $product_id)
    {
        return DB::connection('oc')->table('oc_product_description')->where('product_id', $product_id)->first();
    }


    public function getProductImages(int $product_id)
    {
        return DB::connection('oc')->table('oc_product_image')->where('product_id', $product_id)->get();
    }


    public function getProductCategories(int $product_id)
    {
        return DB::connection('oc')->table('oc_product_to_category')->where('product_id', $product_id)->get();
    }


    /**
     * @param string $image
     * @param string $name
     * @param int    $id
     *
     * @return string
     */
    public function resolveProductImage(string $image, string $name, int $id): string
    {
        $response = config('settings.default_product_image');

        if ($image) {
            $time = time() . Str::random(9);

            $image_contents = false;

            try {
                $image_contents = file_get_contents($image);
            } catch (\Exception $e) {
                Log::info('Product ID_' . $id . ' :: ' . $e->getMessage());
            }

            if ($image_contents) {
                $image_saved = Storage::disk('local')->put('temp/' . $id . '.jpg', file_get_contents($image));

                if ($image_saved) {
                    try {
                        $image = Storage::disk('local')->get('temp/' . $id . '.jpg');
                        $img = Image::make($image);
                    } catch (\Exception $e) {
                        Log::info('Error downloading image: ' . $image);
                        Log::info($e->getMessage());
                    }

                    $str = $id . '/' . Str::limit(Str::slug($name)) . '-' . $time . '.';

                    $img = $img->resize(800, null, function ($constraint) {
                        $constraint->aspectRatio();
                    });

                    $path = $str . 'jpg';
                    Storage::disk('products')->put($path, $img->encode('jpg'));

                    $path_webp = $str . 'webp';
                    Storage::disk('products')->put($path_webp, $img->encode('webp'));

                    // Thumb creation
                    $str_thumb = $id . '/' . Str::limit(Str::slug($name)) . '-' . $time . '-thumb.';
                    $canvas = Image::canvas(300, 300, '#ffffff');

                    $img = $img->resize(null, 260, function ($constraint) {
                        $constraint->aspectRatio();
                    });

                    $canvas->insert($img, 'center');

                    $path_webp_thumb = $str_thumb . 'webp';
                    Storage::disk('products')->put($path_webp_thumb, $canvas->encode('webp'));

                    $response = config('filesystems.disks.products.url') . $path;

                    Storage::disk('local')->delete('temp/' . $id . '.jpg');
                }
            }
        }

        return $response;
    }


    /**
     * @param $categories
     *
     * @return array
     */
    public function resolveProductCategories($categories): array
    {
        $response = [];

        foreach ($categories as $category) {
            $exist = Category::query()->where('image', $category->category_id)->first();

            if ($exist) {
                $response[] = $exist->id;
            }
        }

        return $response;
    }


    /**
     * @param string $text
     *
     * @return int
     */
    public function resolveAuthor(string $text = ''): int
    {
        $pos = strpos($text, ':');

        // Try to resolve name from title of the book
        if ($pos) {
            $name = '';
            $meta = '';
            $arr = explode(':', $text);
            $names = explode(' ', $arr[0]);

            if (isset($names[1])) {
                $first = Helper::resolveFirstLetter(trim($names[1]));

                if (count($names) > 2) {
                    if (in_array(trim($names[1]), ['von', 'Von', 'fon', 'Fon']) || strpos(trim($names[1]), '.')) {
                        $first = Helper::resolveFirstLetter(trim($names[2]));
                    }

                    $name = trim($names[1]) . ' ' . trim($names[2]) . ' ' . trim($names[0]);
                    $meta = trim($names[1]) . ' ' . trim($names[2]) . ', ' . trim($names[0]);
                }

                if (count($names) < 3) {
                    $name = trim($names[1]) . ' ' . trim($names[0]);
                    $meta = trim($names[1]) . ', ' . trim($names[0]);
                }
            }

            if ($name == '') {
                return config('settings.unknown_author');
            }
            // Check if author exist.
            $exist = Author::where('title', $name)->first();

            if ( ! $exist) {
                return Author::insertGetId([
                    'letter'           => $first,
                    'title'            => $name,
                    'description'      => '',
                    'meta_title'       => $meta,
                    'meta_description' => '',
                    'image'            => 'media/avatars/avatar0.jpg',
                    'lang'             => 'hr',
                    'sort_order'       => 0,
                    'status'           => 1,
                    'slug'             => Str::slug($name),
                    'url'              => config('settings.author_path') . '/' . Str::slug($name),
                    'created_at'       => Carbon::now(),
                    'updated_at'       => Carbon::now()
                ]);
            }

            return $exist->id;
        }

        return config('settings.unknown_author');
    }


    /**
     * @param string $text
     *
     * @return array
     */
    public function resolveAttributes(string $text = '', int $id = 0): array
    {
        $response = [];
        $description = $text;
        $arr = explode('</p>', $text);

        foreach ($arr as $item) {
            $item = trim(str_replace('<p>', '', $item));

            if ($item) {
                $params = explode(':', $item);

                if (isset($params[1])) {
                    $response[$params[0]] = $params[1];
                }
            }
        }

      /*  foreach ($arr as $item) {
            $item = trim(str_replace('p', '', $item));

            if ($item) {
              //  $params = explode(':', $item);

                if (isset($params[1])) {
                    $response[$params[0]] = $params[1];
                }
            }
        }*/

        foreach ($response as $key => $item) {
            if (in_array($key, ['Izdavač', 'izdavač', 'IZDAVAČ'])) {
                $response['Izdavač'] = $item;

                $search = '<p>' . $key . ':' . $item . '</p>';
                $description = str_replace($search, '', $description);
            }
            if (in_array($key, ['Šifra', 'šifra', 'ŠIFRA'])) {
                $response['Šifra'] = $item;

                $search = '<p>' . $key . ':' . $item . '</p>';
                $description = str_replace($search, '', $description);
            }
            if (in_array($key, ['Broj stranica', 'broj stranica', 'BROJ STRANICA', 'STRANICA', 'Stranica', 'stranica'])) {
                $response['Broj stranica'] = $item;

                $search = '<p>' . $key . ':' . $item . '</p>';
                $description = str_replace($search, '', $description);
            }
            if (in_array($key, ['Jezik', 'jezik', 'JEZIK'])) {
                $response['Jezik'] = $item;

                $search = '<p>' . $key . ':' . $item . '</p>';
                $description = str_replace($search, '', $description);
            }
            if (in_array($key, ['Pismo', 'pismo', 'PISMO'])) {
                $response['Pismo'] = $item;

                $search = '<p>' . $key . ':' . $item . '</p>';
                $description = str_replace($search, '', $description);
            }
            if (in_array($key, ['Stanje', 'stanje', 'STANJE'])) {
                $response['Stanje'] = $item;

                $search = '<p>' . $key . ':' . $item . '</p>';
                $description = str_replace($search, '', $description);
            }
            if (in_array($key, ['Uvez', 'uvez', 'UVEZ'])) {
                $response['Uvez'] = $item;

                $search = '<p>' . $key . ':' . $item . '</p>';
                $description = str_replace($search, '', $description);
            }
            if (in_array($key, ['Godina', 'godina', 'GODINA'])) {
                $response['Godina'] = $item;

                $search = '<p>' . $key . ':' . $item . '</p>';
                $description = str_replace($search, '', $description);
            }
        }

        Product::query()->where('id', $id)->update([
            'description' => $description
        ]);

        return $response;
    }


    /**
     * @param string $publisher
     *
     * @return int
     */
    public function resolvePublisher(string $publisher = null): int
    {
        if ($publisher) {
            $exist = Publisher::where('title', $publisher)->first();

            if ( ! $exist) {
                return Publisher::insertGetId([
                    'letter'           => Helper::resolveFirstLetter($publisher),
                    'title'            => $publisher,
                    'description'      => '',
                    'meta_title'       => $publisher,
                    'meta_description' => '',
                    'lang'             => 'hr',
                    'sort_order'       => 0,
                    'status'           => 1,
                    'slug'             => Str::slug($publisher),
                    'url'              => config('settings.publisher_path') . '/' . Str::slug($publisher),
                    'created_at'       => Carbon::now(),
                    'updated_at'       => Carbon::now()
                ]);
            }

            return $exist->id;
        }

        return config('settings.unknown_publisher');
    }


    /**
     * @param int|null $offset
     * @param int|null $limit
     *
     * @return bool|\Illuminate\Support\Collection|mixed|string
     */
    public function resolveProductsImportRange(int $offset = null, int $limit = null)
    {
        if ( ! $offset && ! $limit) {
            $set = $this->getImportRange();

            if ( ! is_string($set) && ! $set->count()) {
                $data = ['offset' => 0, 'limit' => 1000];

                $this->setImportRange($data);

                $set = $this->getImportRange();
            }

            return $set;
        }

        $data = ['offset' => $offset, 'limit' => $limit];

        $this->setImportRange($data);

        return $this->getImportRange();
    }


    /**
     * @return false|\Illuminate\Support\Collection
     */
    private function getImportRange()
    {
        return Settings::get('import', 'range');
    }


    /**
     * @param array $data
     *
     * @return bool|mixed
     */
    private function setImportRange(array $data)
    {
        return Settings::reset('import', 'range', $data);
    }

}
