<?php

namespace App\Http\Controllers\Api\v2;

use App\Helpers\Helper;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Product\ProductImage;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function autocomplete(Request $request)
    {
        $query = (new Product())->newQuery();

        if ($request->has('query')) {
            $query->where('name', 'like', '%' . $request->input('query') . '%')
                  ->orWhere('sku', 'like', '%' . $request->input('query'));
        }

        $products = $query->get();

        return response()->json($products);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroyImage(Request $request)
    {
        $image = ProductImage::where('id', $request->input('data'))->first();

        if (isset($image->image)) {
            $path = str_replace(config('filesystems.disks.products.url'), '', $image->image);
            // Obriši staru sliku
            Storage::disk('products')->delete($path);

            if (ProductImage::where('id', $request->input('data'))->delete()) {
                ProductImage::where('image', $image->image)->delete();

                return response()->json(['success' => 200]);
            }
        }

        return response()->json(['error' => 400]);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeStatus(Request $request)
    {
        if ($request->has('id')) {
            $product = Product::where('id', $request->input('id'))->first();

            if ($product) {
                if ($request->input('value')) {
                    $product->update([
                        'status' => 1,
                        'quantity' => $product->quantity ?: 1
                    ]);
                } else {
                    $product->update([
                        'status' => 0,
                        'quantity' => 0
                    ]);
                }

                return response()->json(['success' => 200]);
            }
        }

        return response()->json(['error' => 400]);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateItem(Request $request)
    {
        if ($request->has('product')) {
            $product = $request->input('product');
            $target = $product['target'];

            if ($product['item'][$target] != $product['new_value']) {
                // If update price
                if ($target == 'price' && $product['item']['special']) {
                    $discount = Helper::calculateDiscount($product['item']['price'], $product['item']['special']);
                    $new_special = Helper::calculateDiscountPrice($product['new_value'], $discount, 'P');

                    Product::where('id', $product['item']['id'])->update([
                        'special' => $new_special
                    ]);
                }

                Product::where('id', $product['item']['id'])->update([
                    $target => $product['new_value']
                ]);

                return response()->json([
                    'success' => 200,
                    'value_1' => $product['new_value'],
                    'value_2' => isset($new_special) ? $new_special : null
                ]);
            }
        }

        return response()->json(['error' => 300]);
    }
}
