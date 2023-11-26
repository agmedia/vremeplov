<?php

namespace App\Models\Back\Orders;

use App\Models\Back\Catalog\Product\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class OrderProduct extends Model
{

    /**
     * @var string
     */
    protected $table = 'order_products';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function product()
    {
        return $this->hasOne(Product::class, 'id', 'product_id');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function real()
    {
        return $this->hasOne(\App\Models\Front\Catalog\Product::class, 'id', 'product_id');
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeLast($query, $count = 9)
    {
        return $query->orderBy('created_at', 'desc')->limit($count);
    }


    /**
     * @param $products
     * @param $order_id
     *
     * @return bool
     */
    public static function store($products, $order_id)
    {
        self::where('order_id', $order_id)->delete();

        foreach ($products as $product) {
            $discount = null;

            if ($product->price < $product->org_price) {
                $discount = (intval($product->price) / intval($product->org_price) * 100) - 100;
            }

            $id = self::insertGetId([
                'order_id'   => $order_id,
                'product_id' => $product->id,
                'name'       => $product->name,
                'quantity'   => $product->quantity,
                'org_price'  => $product->org_price,
                'discount'   => $discount,
                'price'      => $product->price,
                'total'      => $product->total,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
        }

        if ( ! $id) {
            return false;
        }

        return true;
    }


    /**
     * @param $products
     * @param $order_id
     *
     * @return bool
     */
    public function make($request, $order_id)
    {
        $data     = json_decode($request->order_data);
        $products = $data->items;

        foreach ($products as $product) {
            $price    = $product->price;
            $model    = $product->associatedModel;
            $discount = null;

            if ( ! empty($product->conditions)) {
                $price = $product->price - $product->conditions->parsedRawValue;
            }

            if (isset($model->action) && ! empty($model->action)) {
                $discount = $model->action->discount;

                if ($model->action->price) {
                    $discount = (($model->action->price / intval($model->price)) * 100) - 100;
                }
            }

            $id = $this->insertGetId([
                'order_id'   => $order_id,
                'product_id' => $product->id,
                'name'       => $product->name,
                'quantity'   => $product->quantity,
                'org_price'  => $product->price,
                'discount'   => $discount,
                'price'      => $price,
                'total'      => $product->quantity * $price,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
        }

        if ( ! $id) {
            return false;
        }

        return true;
    }
}
