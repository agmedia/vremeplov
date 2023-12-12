<?php

namespace App\Http\Controllers\Back;

use App\Helpers\Chart;
use App\Helpers\Helper;
use App\Helpers\Import;
use App\Helpers\ProductHelper;
use App\Http\Controllers\Controller;
use App\Imports\ProductImport;
use App\Mail\OrderReceived;
use App\Mail\OrderSent;
use App\Models\Back\Catalog\Author;
use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\Mjerilo;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Product\ProductCategory;
use App\Models\Back\Catalog\Product\ProductImage;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Marketing\Review;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderProduct;
use App\Models\Back\Settings\Api\OC_Import;
use App\Models\Back\Settings\Settings;
use App\Models\User;
use App\Models\UserDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Bouncer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use function Symfony\Component\Translation\t;

class DashboardController extends Controller
{

    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index()
    {
        $data['today']            = Order::whereDate('created_at', Carbon::today())->count();
        $data['proccess']         = Order::whereIn('order_status_id', [1, 2, 3])->count();
        $data['finished']         = Order::whereIn('order_status_id', [4, 5, 6, 7])->count();
        $data['this_month']       = Order::whereMonth('created_at', '=', Carbon::now()->month)->count();
        $data['this_month_total'] = Order::whereMonth('created_at', '=', Carbon::now()->month)->whereIn('order_status_id', [4, 1, 2, 3])->sum('total');

        $data['users'] = UserDetail::whereIn('role', ['customer'])->count();

        $data['comments']     = Review::whereIn('status', ['0'])->count();
        $data['zeroproducts'] = Product::whereIn('quantity', ['0'])->count();

        $orders = Order::last()->with('products')->get();

        $ordersfinished = Order::finished()->with('products')->get();
        $products       = $ordersfinished->map(function ($item) {
            return $item->products()->get();
        })->take(9)->flatten();

        $bestsellers = DB::table('order_products')
                         ->leftJoin('orders', 'orders.id', '=', 'order_products.order_id')
                         ->select('order_products.name', 'order_products.product_id',
                             DB::raw('SUM(order_products.quantity) as total'))
                         ->groupBy('order_products.product_id')
                         ->whereIn('orders.order_status_id', [1, 2, 3, 4])
                         ->orderBy('total', 'desc')
                         ->limit(10)
                         ->get();

        $chart     = new Chart();
        $this_year = json_encode($chart->setDataByYear(
            Order::chartData($chart->setQueryParams())
        ));
        $last_year = json_encode($chart->setDataByYear(
            Order::chartData($chart->setQueryParams(true))
        ));

        // dd($data['users']);

        return view('back.dashboard', compact('data', 'orders', 'bestsellers', 'products', 'this_year', 'last_year'));
    }


    /**
     * Import initialy from Excel files.
     *
     * @param Request $request
     */
    public function importCategories(Request $request)
    {
        $groups = [];
        $count = 0;
        $import = new OC_Import();
        $categories  = $import->getCategories();

        if ($categories->count()) {
            foreach ($categories as $category) {
                $count++;
                $main_description = $import->getCategoryDescription($category->category_id);
                $main_path        = $import->getCategoryPath($category->category_id);

                $groups[$category->category_id] = [
                    'id' => $count,
                    'title' => $main_description->name,
                    'slug'  => $main_path->keyword,
                    'sort_order' => $category->sort_order,
                    'status' => 1
                ];

                $subcategories = $import->getCategories($category->category_id);

                if ($subcategories->count()) {
                    foreach ($subcategories as $subcategory) {
                        $count++;
                        $submain_description = $import->getCategoryDescription($subcategory->category_id);
                        $submain_path        = $import->getCategoryPath($subcategory->category_id);

                        $subcategory_exist = Category::query()->where('slug', $submain_path->keyword)->first();

                        if ( ! $subcategory_exist) {
                            $new_subcategory = $import->saveCategory(
                                $submain_description->name,
                                $groups[$category->category_id]['slug'],
                                $submain_path->keyword,
                                $submain_description->meta_title,
                                $submain_description->meta_description,
                                0,
                                $subcategory->sort_order,
                                $subcategory->category_id
                            );
                        }

                        $sub_subcategories = $import->getCategories($subcategory->category_id);

                        if ($sub_subcategories->count()) {
                            foreach ($sub_subcategories as $sub_subcategory) {
                                $count++;
                                $sub_submain_description = $import->getCategoryDescription($sub_subcategory->category_id);
                                $sub_submain_path        = $import->getCategoryPath($sub_subcategory->category_id);

                                $sub_subcategory_exist = Category::query()->where('slug', $sub_submain_path->keyword)->first();

                                if ( ! $sub_subcategory_exist) {
                                    $new_subcategory = $import->saveCategory(
                                        $sub_submain_description->name,
                                        $groups[$category->category_id]['slug'],
                                        $sub_submain_path->keyword,
                                        $sub_submain_description->meta_title,
                                        $sub_submain_description->meta_description,
                                        $subcategory_exist ? $subcategory_exist->id : $new_subcategory,
                                        $sub_subcategory->sort_order,
                                        $sub_subcategory->category_id
                                    );
                                }
                            }
                        }
                    }
                }
            }

            $groups = collect($groups)->sortBy('title')->toArray();
            $groups = array_values($groups);

            $settings = Settings::where('code', 'category')->where('key', 'list.groups')->first();

            if ($settings) {
                Settings::edit($settings->id, 'category', 'list.groups', json_encode($groups), true);
            } else {
                Settings::insert('category', 'list.groups', json_encode($groups), true);
            }
        }

        return redirect()->route('dashboard')->with(['success' => 'Import je uspješno obavljen..! ' . $count . ' kategorija importano.']);
    }


    public function importProducts(Request $request)
    {
        $count = 0;
        $import = new OC_Import();

        $products = $import->getProducts(0, 50);

        $existing = Product::query()->pluck('ean');

        $diff = $products->pluck('product_id')->diff($existing)->toArray();

        $products = $import->getProducts($diff);

        foreach ($products as $product) {
            $product_description = $import->getProductDescription($product->product_id);
            $product_images = $import->getProductImages($product->product_id);
            $product_categories = $import->getProductCategories($product->product_id);

            $attributes = $import->resolveAttributes($product_description->description);
            $author = $import->resolveAuthor($product_description->name);
            $publisher = $import->resolvePublisher(isset($attributes['Izdavač']) ? $attributes['Izdavač'] : '');

            $product_id = Product::insertGetId([
                'author_id'        => $author,
                'publisher_id'     => $publisher,
                'action_id'        => 0,
                'name'             => $product_description->name,
                'sku'              => isset($attributes['Šifra']) ? $attributes['Šifra'] : $product->model,
                'ean'              => $product->product_id,
                'polica'           => 0,
                'description'      => '<p>' . str_replace('\n', '<br>', $product_description->description) . '</p>',
                'slug'             => Str::slug($product_description->name) . '-' . time(),
                'url'              => '',
                'price'            => $product->price,
                'quantity'         => $product->quantity,
                'decrease'         => 1,
                'tax_id'           => 1,
                'special'          => null,
                'special_from'     => null,
                'special_to'       => null,
                'meta_title'       => $product_description->meta_title,
                'meta_description' => $product_description->meta_description,
                'pages'            => isset($attributes['Broj stranica']) ? $attributes['Broj stranica'] : null,
                'dimensions'       => null,
                'origin'           => isset($attributes['Jezik']) ? $attributes['Jezik'] : null,
                'letter'           => isset($attributes['Pismo']) ? $attributes['Pismo'] : null,
                'condition'        => isset($attributes['Stanje']) ? $attributes['Stanje'] : null,
                'binding'          => isset($attributes['Uvez']) ? $attributes['Uvez'] : null,
                'year'             => isset($attributes['Godina']) ? str_replace('.', '', $attributes['Godina']) : null,
                'viewed'           => 0,
                'sort_order'       => 0,
                'push'             => 0,
                'status'           => $product->quantity ? 1 : 0,
                'created_at'       => Carbon::now(),
                'updated_at'       => Carbon::now()
            ]);

            if ($product_id) {
                // Create, sort all images.
                $main_path = 'https://www.antikvarijat-vremeplov.hr/image/' . $product->image;
                $main_image = $import->resolveProductImage($main_path, $product_description->name, $product_id);

                Product::where('id', $product_id)->update(['image' => $main_image]);

                if ($product_images->count()) {
                    $icount = 0;
                    foreach ($product_images as $product_image) {
                        $path = 'https://www.antikvarijat-vremeplov.hr/image/' . $product_image->image;
                        $image = $import->resolveProductImage($path, $product_description->name, $product_id);

                        ProductImage::insert([
                            'product_id' => $product_id,
                            'image'      => $image,
                            'alt'        => $product_description->name,
                            'published'  => 1,
                            'sort_order' => $icount,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now()
                        ]);

                        $icount++;
                    }
                }

                $categories = $import->resolveProductCategories($product_categories);

                if ($categories) {
                    foreach ($categories as $category) {
                        ProductCategory::insert([
                            'product_id'  => $product_id,
                            'category_id' => $category
                        ]);
                    }
                }

                $product = Product::find($product_id);

                $product->update([
                    'url'             => ProductHelper::url($product),
                    'category_string' => ProductHelper::categoryString($product)
                ]);

                $count++;
            }
        }

        return redirect()->route('dashboard')->with(['success' => 'Import je uspješno obavljen..! ' . $count . ' proizvoda importano.']);
    }


    public function importCustomers()
    {
        
    }


    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function letters()
    {
        $authors = Author::all();

        foreach ($authors as $author) {
            $letter = Helper::resolveFirstLetter($author->title);

            $author->update([
                'letter' => Str::ucfirst($letter)
            ]);
        }

        //
        $publishers = Publisher::all();

        foreach ($publishers as $publisher) {
            $letter = Helper::resolveFirstLetter($publisher->title);

            $publisher->update([
                'letter' => Str::ucfirst($letter)
            ]);
        }

        return redirect()->route('dashboard');
    }


    /**
     *
     */
    public function slugs()
    {
        $slugs = Product::query()->groupBy('slug')->havingRaw('COUNT(id) > 1')->pluck('slug', 'id')->toArray();

        foreach ($slugs as $slug) {
            $products = Product::where('slug', $slug)->get();

            if ($products) {
                foreach ($products as $product) {
                    $time = Str::random(9);
                    $product->update([
                        'slug' => $product->slug . '-' . $time,
                        'url'  => $product->url . '-' . $time,
                    ]);
                }
            }
        }

        return redirect()->route('dashboard');
    }


    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function statuses()
    {
        // AUTHORS
        $products = Product::query()
                           ->where('quantity', '>', 0)
                           ->select('author_id', DB::raw('count(*) as total'))
                           ->groupBy('author_id')
                           ->pluck('author_id')
                           ->unique();

        $authors = Author::query()->pluck('id')->diff($products)->flatten();

        Author::whereIn('id', $authors)->update([
            'status'     => 0,
            'updated_at' => now()
        ]);

        Author::whereNotIn('id', $authors)->update([
            'status'     => 1,
            'updated_at' => now()
        ]);

        // PUBLISHERS
        $products = Product::query()
                           ->where('quantity', '>', 0)
                           ->select('publisher_id', DB::raw('count(*) as total'))
                           ->groupBy('publisher_id')
                           ->pluck('publisher_id')
                           ->unique();

        $publishers = Publisher::query()->pluck('id')->diff($products)->flatten();

        Publisher::whereIn('id', $publishers)->update([
            'status'     => 0,
            'updated_at' => now()
        ]);

        Publisher::whereNotIn('id', $publishers)->update([
            'status'     => 1,
            'updated_at' => now()
        ]);

        // CATEGORIES
        $categories_off = Category::query()->select('id')->withCount('products')->having('products_count', '<', 1)->get()->toArray();

        if ($categories_off) {
            foreach ($categories_off as $category) {
                Category::where('id', $category['id'])->update([
                    'status'     => 0,
                    'updated_at' => now()
                ]);
            }
        }

        $categories_on = Category::query()->select('id')->withCount('products')->having('products_count', '>', 0)->get()->toArray();

        if ($categories_on) {
            foreach ($categories_on as $category) {
                Category::where('id', $category['id'])->update([
                    'status'     => 1,
                    'updated_at' => now()
                ]);
            }
        }

        // PRODUCTS
        $products = Product::where('quantity', 0)->pluck('id');

        Product::whereIn('id', $products)->update([
            'status'     => 0,
            'updated_at' => now()
        ]);

        Product::whereNotIn('id', $products)->update([
            'status'     => 1,
            'updated_at' => now()
        ]);

        return redirect()->route('dashboard');
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function mailing(Request $request)
    {
        $order = Order::where('id', 3)->first();

        dispatch(function () use ($order) {
            Mail::to(config('mail.admin'))->send(new OrderReceived($order));
            Mail::to($order->payment_email)->send(new OrderSent($order));
        });

        return redirect()->route('dashboard');
    }


    /**
     *
     */
    public function duplicate(string $target = null)
    {
        // Duplicate images
        if ($target === 'images') {
            $paths = ProductImage::query()->groupBy('image')->havingRaw('COUNT(id) > 1')->pluck('image', 'id')->toArray();

            foreach ($paths as $path) {
                $first = ProductImage::where('image', $path)->first();

                ProductImage::where('image', $path)->where('id', '!=', $first->id)->delete();
            }
        }

        // Duplicate publishers
        if ($target === 'publishers') {
            $paths = Publisher::query()->groupBy('title')->havingRaw('COUNT(id) > 1')->pluck('title', 'id')->toArray();

            foreach ($paths as $id => $path) {
                $group = Publisher::where('title', $path)->get();

                foreach ($group as $item) {
                    if ($item->id != $id) {
                        foreach ($item->products()->get() as $product) {
                            Product::where('id', $product->id)->update([
                                'publisher_id' => $id
                            ]);
                        }

                        Publisher::where('id', $item->id)->delete();
                    }
                }
            }
        }

        return redirect()->route('dashboard');
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function setCategoryGroup(Request $request)
    {
        Category::query()->update([
            'group' => Helper::categoryGroupPath(true)
        ]);

        $products = Product::query()->where('push', 0)->get();

        foreach ($products as $product) {
            $product->update([
                'url'             => ProductHelper::url($product),
                'category_string' => ProductHelper::categoryString($product),
                'push'            => 1
            ]);
        }

        return redirect()->route('dashboard');
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function setProductsUnlimitedQty(Request $request)
    {
        $products = ProductCategory::query()->where('category_id', 25)->pluck('product_id');

        Product::query()->whereIn('id', $products)->update([
            'quantity' => 100,
            'decrease' => 0,
            'status'   => 1
        ]);

        return redirect()->route('dashboard')->with(['success' => 'Proizvodi su namješteni na neograničenu količinu..! ' . $products->count() . ' proizvoda obnovljeno.']);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function setPdvProducts(Request $request)
    {
        $ids = ProductCategory::query()->whereIn('category_id', [174, 175])->pluck('product_id');

        Product::query()->whereIn('id', $ids)->update([
            'tax_id' => 2
        ]);

        return redirect()->route('dashboard')->with(['success' => 'PDV je obnovljen na kategoriji svezalice..! ' . $ids->count() . ' proizvoda obnovljeno.']);
    }

}
