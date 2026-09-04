<?php

namespace App\Models;

use App\Helpers\Helper;
use App\Models\Back\Orders\Order;
use App\Models\Front\Catalog\Product;
use Darryldecode\Cart\CartCollection;
use function Livewire\str;

/**
 * Class Sitemap
 * @package App\Models
 */
class TagManager
{

    /**
     * @param Order $order
     *
     * @return array
     */
    public static function getGoogleSuccessDataLayer(Order $order)
    {
        $products = [];
        $shipping = 0;
        $tax      = 0;

        foreach ($order->products as $product) {
            if ($product->real) {
                $item = static::getGoogleProductDataLayer($product->real);
                $item['quantity'] = (int) $product->quantity;
                $item['price'] = round((float) $product->price, 2);
            } else {
                $item = [
                    'item_id' => (string) $product->product_id,
                    'item_name' => (string) $product->name,
                    'price' => round((float) $product->price, 2),
                    'currency' => 'EUR',
                    'discount' => round(abs((float) ($product->discount ?? 0)), 2),
                    'quantity' => (int) $product->quantity,
                ];
            }

            $products[] = $item;
        }

        foreach ($order->totals()->get() as $total) {
            if ($total->code == 'subtotal') {
                $tax += $total->value - ($total->value / 1.05);
            }
            if ($total->code == 'shipping') {
                $tax      += $total->value - ($total->value / 1.25);
                $shipping = $total->value;
            }
        }

        $data = [
            'event'     => 'purchase',
            'ecommerce' => [
                'transaction_id' => (string) $order->id,
                'affiliation'    => 'Antikvarijat Vremeplov webshop',
                'value'          => round((float) $order->total, 2),
                'tax'            => round((float) $tax, 2),
                'shipping'       => round((float) $shipping, 2),
                'currency'       => 'EUR',
                'items'          => $products
            ],
        ];

        return $data;
    }


    /**
     * @param Product $product
     *
     * @return array
     */
    public static function getGoogleProductDataLayer(Product $product): array
    {
        $discount = 0;

        if ($product->main_price > $product->main_special) {
            $discount = Helper::calculateDiscount($product->main_price, $product->main_special);
        }

        $item = [
            'item_id'        => $product->sku,
            'item_name'      => $product->name,
            'price'          => round((float) str_replace(',', '.', $product->main_price), 2),
            'currency'       => 'EUR',
            'discount'       =>  (float) number_format($discount, 2),
            'item_category'  => $product->category() ? $product->category()->title : '',
            'item_category2' => $product->subcategory() ? $product->subcategory()->title : '',
            'quantity'       => 1,
        ];

        return $item;
    }



    /**
     * @param CartCollection $cart_collection
     *
     * @return array
     */
    public static function getGoogleCartDataLayer(array $cart_collection): array
    {
        $items = [];

        foreach ($cart_collection['items'] as $item) {
            $data = $item->associatedModel->dataLayer;
            $data['quantity'] = (int) $item->quantity;
            $items[] = $data;
        }

        return $items;
    }

}
