<?php

namespace App\Models\Back\Settings\Api;

use App\Helpers\ApiHelper;
use App\Helpers\Csv;
use App\Helpers\Helper;
use App\Helpers\Import;
use App\Helpers\ProductHelper;
use App\Helpers\Query;
use App\Mail\akmkSendReport;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Product\ProductCategory;
use App\Models\Back\Orders\Order;
use App\Models\Back\Settings\Settings;
use App\Models\Back\TempTable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AkademskaKnjigaMk
{

    /**
     * @var array|null
     */
    protected $request;

    /**
     * @var string[]
     */
    protected $excel_keys = ['sku', 'isbn', 'Naslov', 'Količina'];


    /**
     * @param array $request
     *
     * @return false|\Illuminate\Http\JsonResponse|int|string
     */
    public function process(array $request)
    {
        if ($request) {
            $this->request = $request;

            switch ($this->request['method']) {
                case 'check-products':
                    return $this->checkProductsForImport();
                case 'products':
                    return $this->importNewProducts();
                case 'update-prices-quantities':
                    return $this->updatePriceAndQuantity();
                case 'send-report':
                    return $this->sendExcelReport();
            }
        }

        return false;
    }


    /**
     * Cca: 25 - 30 sec.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    private function checkProductsForImport(bool $diff = true)
    {
        TempTable::query()->truncate();

        $limit = '60000';
        $data  = Http::get('http://akademskakniga.mk/api/ASyn/' . $limit);

        if ($diff) {
            $existing = Product::query()->pluck('sku');
            $list     = collect($data->json())->pluck('bookId')
                                              ->diff($existing)
                                              ->flatten();

            $for_import = collect($data->json())->whereIn('bookId', $list)->chunk(10000);

        } else {
            $for_import = collect($data->json())->chunk(10000);
        }

        foreach ($for_import->all() as $item_list) {
            $query = [];
            foreach ($item_list as $item) {
                $query[] = [
                    'sku'      => (string) $item['bookId'],
                    'quantity' => $item['inStock'],
                    'price'    => $this->getPrice($item['price']),
                    'special'  => $item['priceWithDiscount'],
                ];
            }

            TempTable::query()->insert($query);
        }

        $count = TempTable::query()->count();

        return ApiHelper::response(1, 'Ima ' . $count . ' novih artikala za import.');
    }


    /**
     * @param $price
     *
     * @return float|int
     */
    private function getPrice($price)
    {
        $price = $price * 1.05;
        $round = floor($price);
        $diff  = $price - $round;

        if ($diff < 0.5) {
            $price = $round + 0.5;
        } else {
            $price = $round + 1;
        }

        return $price;
    }


    /**
     * @return int
     */
    private function importNewProducts()
    {
        $count      = 0;
        $for_import = TempTable::query()->take(1000)->get();

        foreach ($for_import as $item) {
            $exist = Product::query()->where('sku', $item['sku'])->first();

            if ( ! $exist) {
                $import = new Import();
                $data   = Http::get('https://akademskakniga.mk/api/Akniga/' . $item['sku'])->json();

                if (is_array($data) && ! empty($data)) {
                    $publisher_id = 0;
                    $author_id    = 0;

                    if (isset($data['bookPublisherId']['bookPublisherName'])) {
                        $publisher_id = $import->resolvePublisher($data['bookPublisherId']['bookPublisherName']);
                    }
                    if ($data['author']) {
                        $author_id = $import->resolveAuthor($data['author']);
                    }

                    $id = Product::query()->insertGetId([
                        'author_id'            => $author_id,
                        'publisher_id'         => $publisher_id,
                        'action_id'            => 0,
                        'name'                 => $data['title'],
                        'sku'                  => $data['bookId'],
                        'polica'               => null,
                        'isbn'                 => $data['ISBN'],
                        'description'          => $data['description'],
                        'slug'                 => Helper::resolveSlug($data, 'title'),
                        'price'                => $item->price,
                        'quantity'             => $item->quantity ?: 0,
                        'decrease'             => 1,
                        'tax_id'               => config('settings.default_tax_id'),
                        'special'              => null,
                        'special_from'         => null,
                        'special_to'           => null,
                        'meta_title'           => $data['title'],
                        'meta_description'     => $data['title'].' - '.$data['author'].' - '.$data['bookPublisherId']['bookPublisherName'],
                        'pages'                => $data['numberOfPages'],
                        'dimensions'           => null,
                        'origin'               => 'Engleski',
                        'letter'               => null,
                        'condition'            => null,
                        'binding'              => $data['formaCover'],
                        'year'                 => $data['yearPublished'],
                        'shipping_time'        => '10-15 dana',
                        'youtube_product_url'  => '',
                        'youtube_channel'      => '',
                        'goodreads_author_url' => '',
                        'goodreads_book_url'   => '',
                        'author_web_url'       => '',
                        'serial_web_url'       => '',
                        'wiki_url'             => '',
                        'viewed'               => 0,
                        'sort_order'           => 0,
                        'push'                 => 0,
                        'status'               => $item->quantity ? 1 : 0,
                        'created_at'           => Carbon::now(),
                        'updated_at'           => Carbon::now()
                    ]);

                    if ($id) {
                        $image = config('settings.image_default');
                        try {
                            $image = $import->resolveImages($data['imageBig'], $data['title'], $id);
                        } catch (\ErrorException $e) {
                            Log::info('Image not imported. Product SKU: (' . $data['bookId'] . ') - ' . $data['title']);
                            Log::info($e->getMessage());
                        }

                        $categories = $import->resolveCategories($data['Kategorii']);

                        ProductCategory::storeData($categories, $id);

                        $product = Product::query()->find($id);

                        $product->update([
                            'image'           => $image,
                            'url'             => ProductHelper::url($product),
                            'category_string' => ProductHelper::categoryString($product)
                        ]);

                        $count++;

                        TempTable::query()->where('sku', $data['bookId'])->delete();
                    }
                }
            }
        }

        return ApiHelper::response(1, 'Importano je ' . $count . ' novih artikala u bazu.');
    }


    /**
     * @return string
     */
    private function updatePriceAndQuantity()
    {
        $this->checkProductsForImport(false);

        Query::run("UPDATE products p INNER JOIN temp_table tt ON p.sku = tt.sku SET p.quantity = tt.quantity, p.price = tt.price;");

        $count = TempTable::query()->get()->count();

        return ApiHelper::response(1, 'Obnovljene su cijene i količine na ' . $count . ' artikala.');
    }


    /**
     * @return string
     */
    public function sendExcelReport()
    {
        $orders = Order::query()->whereDate('created_at', today()->subDay())->with('products')->get();
        $products = collect();

        if ($orders->count()) {
            foreach ($orders as $order) {
                foreach ($order->products as $product) {
                    $products->push($product);
                }
            }

            $products->groupBy('product_id')->all();
        }

        $to_send = [];

        foreach ($products->groupBy('product_id')->all() as $group) {
            $qty = 0;

            foreach ($group as $product) {
                $qty += $product->quantity;
            }

            $to_send[] = [
                'sku' => $product->product->sku,
                'isbn' => $product->product->isbn,
                'title' => $product->product->name,
                'quantity' => $qty,
            ];
        }

        $csv = new Csv();
        $csv->createExcelFile('akmk_report.xlsx', $to_send, $this->excel_keys);

        dispatch(function () {
            Mail::to('aleksandar@aleksandarpavlovski.com')->send(new akmkSendReport());
        });

        return ApiHelper::response(1, 'Excel je poslan.');
    }

}
