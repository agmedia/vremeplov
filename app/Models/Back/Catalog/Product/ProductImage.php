<?php

namespace App\Models\Back\Catalog\Product;

use App\Helpers\ProductHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class ProductImage extends Model
{

    /**
     * @var string
     */
    protected $table = 'product_images';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * @var Model
     */
    protected $resource;


    /**
     * @param $resource
     * @param $request
     *
     * @return mixed
     */
    public function store($resource, $request)
    {
        $this->resource = $resource;
        $existing       = isset($request['slim']) ? $request['slim'] : null;
        $new            = isset($request['files']) ? $request['files'] : null;

        //dd($request, $resource, $existing, $new);

        // Ako ima novih slika
        if ($new) {
            foreach ($new as $new_image) {
                if (isset($new_image['image']) && $new_image['image']) {
                    $data = json_decode($new_image['image']);
                    $sort_order = (isset($new_image['sort_order']) && $new_image['sort_order']) ? $new_image['sort_order'] : 0;

                    $saved = $this->saveNew($data->output, $sort_order);
                    // Ako je novi default ujedno i novo uploadana fotka.
                    // Također ako je ime novo uploadane slike isto kao $existing['default']
                    if (
                        isset($new['default']) &&
                        strpos($new['default'], 'image/') !== false &&
                        $data->output->name == str_replace('image/', '', $new['default'])
                    ) {
                        $this->switchDefault($saved);
                    }
                }
            }
        }

        if ($existing) {
            // Ako se mijenja default i nismo ga već promjenili...
            if (isset($existing['default']) && $existing['default'] != 'on') {
                $this->switchDefault(
                    $this->where('id', $existing['default'])->first()
                );
            }

            foreach ($existing as $key => $image) {
                if (isset($image['image']) && $image['image']) {
                    $data = json_decode($image['image']);

                    if ($data) {
                        $this->replace($key, $data->output, $image['title']);
                    }
                }

                if ( ! $key) {
                    $this->saveMainTitle($image['title']);
                    //$this->saveMainTitle($image['title'], $image['alt']);
                    // zamjeni title na glavnoj
                }

                if ($key && $key != 'default') {
                    $published = (isset($image['published']) && $image['published'] == 'on') ? 1 : 0;

                    $this->where('id', $key)->update([
                        'alt'        => $image['alt'],
                        'sort_order' => $image['sort_order'],
                        'published'  => $published
                    ]);

                    $this->saveTitle($key, $image['title']);
                }
            }
        }

        return $this->where('product_id', $this->resource->id)->get();
    }


    /**
     * @param $id
     * @param $new
     *
     * @return mixed
     */
    public function replace($id, $new, $title)
    {
        // Nađi staru sliku i izdvoji path
        $old  = $id ? $this->where('id', $id)->first() : $this->resource;
        $path = str_replace('media/images/gallery/products/', '', $old['image']);
        // Obriši staru sliku
        Storage::disk('products')->delete($path);

        $path = $this->saveImage($new->image, $title);

        // Ako nije glavna slika updejtaj path na product_images DB
        if ($id) {
            return $this->where('id', $id)->update([
                'image' => config('filesystems.disks.products.url') . $path
            ]);
        }

        return Product::where('id', $this->resource->id)->update([
            'image' => config('filesystems.disks.products.url') . $path
        ]);
    }


    /**
     * @param $new
     *
     * @return mixed
     */
    public function switchDefault($new)
    {
        //dd($new, $this->resource);
        if (isset($new->id)) {

            if ($this->resource->image) {
                $this->where('id', $new->id)->update([
                    'image' => $this->resource->image
                ]);
            } else {
                $this->where('id', $new->id)->delete();
            }

            Product::where('id', $this->resource->id)->update([
                'image' => $new->image
            ]);
        }

        return $new;
    }


    /**
     * @param $new
     *
     * @return mixed
     */
    public function saveNew($new, $sort_order = 0)
    {
        $path = $this->saveImage($new->image);

        // Store image in product_images DB
        $id = $this->insertGetId([
            'product_id' => $this->resource->id,
            'image'      => config('filesystems.disks.products.url') . $path,
            'alt'        => $this->resource->name,
            'published'  => 1,
            'sort_order' => $sort_order,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        return $this->find($id);
    }


    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * @param string $title
     */
    private function saveMainTitle(string $title/*, string $alt*/)
    {
        $existing_clean = ProductHelper::getCleanImageTitle($this->resource->image);

        if ($existing_clean != $title) {
            $path          = $this->resource->id . '/';
            $existing_full = ProductHelper::getFullImageTitle($this->resource->image);
            $new_full      = ProductHelper::setFullImageTitle($title);

            Storage::disk('products')->move($path . $existing_full . '.jpg', $path . $new_full . '.jpg');
            Storage::disk('products')->move($path . $existing_full . '.webp', $path . $new_full . '.webp');
            Storage::disk('products')->move($path . $existing_full . '-thumb.webp', $path . $new_full . '-thumb.webp');

            Product::where('id', $this->resource->id)->update([
                'image' => config('filesystems.disks.products.url') . $path . $new_full . '.jpg'
            ]);
        }

        /*Product::where('id', $this->resource->id)->update([
            'image_alt' => $alt
        ]);*/
    }


    /**
     * @param int    $id
     * @param string $title
     */
    private function saveTitle(int $id, string $title)
    {
        $resource = $this->where('id', $id)->first();

        if ($resource && isset($resource->image)) {
            $existing_clean = ProductHelper::getCleanImageTitle($resource->image);

            if ($existing_clean != $title) {
                $path          = $this->resource->id . '/';
                $existing_full = ProductHelper::getFullImageTitle($resource->image);
                $new_full      = ProductHelper::setFullImageTitle($title);

                Storage::disk('products')->move($path . $existing_full . '.jpg', $path . $new_full . '.jpg');
                Storage::disk('products')->move($path . $existing_full . '.webp', $path . $new_full . '.webp');
                Storage::disk('products')->move($path . $existing_full . '-thumb.webp', $path . $new_full . '-thumb.webp');

                $this->where('id', $id)->update([
                    'image' => config('filesystems.disks.products.url') . $path . $new_full . '.jpg'
                ]);
            }
        }
    }


    /**
     * @param $image
     *
     * @return string
     */
    private function saveImage($image, $title = null)
    {
        if ( ! $title) {
            $title = $this->resource->name;
        }

        $time = Str::random(4);
        $img  = Image::make($this->makeImageFromBase($image));
        $path = $this->resource->id . '/' . Str::slug($title) . '-' . $time . '.';

        $path_jpg = $path . 'jpg';
        Storage::disk('products')->put($path_jpg, $img->encode('jpg'));

        $path_webp = $path . 'webp';
        Storage::disk('products')->put($path_webp, $img->encode('webp'));

        // Thumb creation
        $path_thumb = $this->resource->id . '/' . Str::slug($title) . '-' . $time . '-thumb.';
        $canvas = Image::canvas(400, 400, '#ffffff');

        $img = $img->resize(null, 350, function ($constraint) {
            $constraint->aspectRatio();
        });

        $canvas->insert($img, 'center');

        $path_webp_thumb = $path_thumb . 'webp';
        Storage::disk('products')->put($path_webp_thumb, $canvas->encode('webp'));

        return $path_jpg;
    }


    /**
     * @param string $base_64_string
     *
     * @return false|string
     */
    private function makeImageFromBase(string $base_64_string)
    {
        $image_parts = explode(";base64,", $base_64_string);

        return base64_decode($image_parts[1]);
    }


    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * @param int $product_id
     *
     * @return Collection
     */
    public static function getAdminList(int $product_id = null): Collection
    {
        $response = [];

        if ($product_id) {
            $images   = self::where('product_id', $product_id)->orderBy('sort_order')->get();

            foreach ($images as $image) {
                $response[] = [
                    'id'         => $image->id,
                    'product_id' => $image->product_id,
                    'image'      => $image->image,
                    'title'      => ProductHelper::getCleanImageTitle($image->image),
                    'alt'        => $image->alt,
                    'published'  => $image->published,
                    'sort_order' => $image->sort_order,
                ];
            }
        }

        return collect($response);
    }


    /**
     * Save stack of images to the
     * product_images database.
     *
     * @param array $paths
     * @param       $product_id
     *
     * @return array|bool
     */
    public static function saveStack(array $paths, $product_id)
    {
        $images = [];

        foreach ($paths as $key => $path) {
            $images[] = self::create([
                'product_id' => $product_id,
                'image'      => $path,
                'sort_order' => $key,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
        }

        if ( ! empty($images)) {
            return $images;
        }

        return false;
    }


    /**
     * Save temporary stored images
     * to newly saved product folder.
     * The folder is based on product ID.
     *
     * @param array $paths
     * @param       $product_id
     *
     * @return array|bool
     */
    public static function transferTemporaryImages(array $paths, $product_id)
    {
        $targets = [];

        foreach ($paths as $key => $path) {
            $target    = str_replace('temp', $product_id, $path);
            $targets[] = $target;

            if ($key == 0) {
                self::setDefault($target, $product_id);
            }

            $_path   = str_replace(config('filesystems.disks.products.url'), '', $path);
            $_target = str_replace(config('filesystems.disks.products.url'), '', $target);

            Storage::disk('products')->move($_path, $_target);
            Storage::disk('products')->delete($_path);
        }

        return self::saveStack($targets, $product_id);
    }


    /**
     * Set default product image.
     *
     * @param string $path
     * @param        $id
     *
     * @return mixed
     */
    public static function setDefault(string $path, $id)
    {
        return Product::where('id', $id)->update([
            'image' => $path
        ]);
    }

}
