<?php

namespace App\Models\Front\Checkout;

use App\Helpers\Helper;
use App\Models\Back\Orders\OrderHistory;
use App\Models\Back\Orders\OrderProduct;
use App\Models\Back\Orders\OrderTotal;
use App\Models\Back\Settings\Settings;
use App\Models\Front\Catalog\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Order extends Model
{

    /**
     * @var string[]
     */
    protected $fillable = ['order_status_id'];

    /**
     * @var array
     */
    public $order = [];

    /**
     * @var null|array
     */
    protected $oc_data = null;


    /**
     * Order constructor.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->order = $data;
    }


    /**
     * @return mixed
     */
    public function getStatusAttribute()
    {
        return $this->status($this->order_status_id);
    }


    /**
     * @param int $id
     *
     * @return mixed
     */
    public function status(int $id)
    {
        $statuses = Settings::get('order', 'statuses');

        return $statuses->where('id', $id)->first();
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products()
    {
        return $this->hasMany(OrderProduct::class, 'order_id')->with('product');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function totals()
    {
        return $this->hasMany(OrderTotal::class, 'order_id')->orderBy('sort_order');
    }


    /**
     * @param int $id
     *
     * @return $this
     */
    public function setData(string $id)
    {
        $data = \App\Models\Back\Orders\Order::where('id', $id)->first();

        if ($data) {
            $this->oc_data = $data;
        }

        return $this;
    }


    /**
     * @return array|null
     */
    public function getData()
    {
        return $this->oc_data;
    }


    /**
     * @param array $data
     *
     * @return bool
     */
    public function createFrom(array $data = [])
    {
        if ( ! empty($data)) {
            $this->order = $data;
        }

        if ( ! empty($this->order) && isset($this->order['cart'])) {
            $user_id = auth()->user() ? auth()->user()->id : 0;

            $order_id = \App\Models\Back\Orders\Order::insertGetId([
                'user_id'          => $user_id,
                'affiliate_id'     => 0,
                'order_status_id'  => $this->order['order_status_id'],
                'invoice'          => '',
                'total'            => $this->order['cart']['total'],
                'payment_fname'    => $this->order['address']['fname'],
                'payment_lname'    => $this->order['address']['lname'],
                'payment_address'  => $this->order['address']['address'],
                'payment_zip'      => $this->order['address']['zip'],
                'payment_city'     => $this->order['address']['city'],
                'payment_state'    => $this->order['address']['state'],
                'payment_phone'    => $this->order['address']['phone'] ?: null,
                'payment_email'    => $this->order['address']['email'],
                'payment_method'   => $this->order['payment']->title,
                'payment_code'     => $this->order['payment']->code,
                'payment_card'     => '',
                'payment_installment' => '',
                'shipping_fname'   => $this->order['address']['fname'],
                'shipping_lname'   => $this->order['address']['lname'],
                'shipping_address' => $this->order['address']['address'],
                'shipping_zip'     => $this->order['address']['zip'],
                'shipping_city'    => $this->order['address']['city'],
                'shipping_state'   => $this->order['address']['state'],
                'shipping_phone'   => $this->order['address']['phone'] ?: null,
                'shipping_email'   => $this->order['address']['email'],
                'shipping_method'  => $this->order['shipping']->title,
                'shipping_code'    => $this->order['shipping']->code,
                'company'          => $this->order['address']['company'],
                'oib'              => $this->order['address']['oib'],
                'created_at'       => Carbon::now(),
                'updated_at'       => Carbon::now()
            ]);

            if ($order_id) {
                // HISTORY
                OrderHistory::insert([
                    'order_id'   => $order_id,
                    'user_id'    => $user_id,
                    'comment'    => config('settings.order.made_text'),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);

                $this->updateProducts($order_id);
                $this->updateTotal($order_id);

                $this->oc_data = \App\Models\Back\Orders\Order::where('id', $order_id)->first();
            }
        }

        return $this;
    }


    /**
     * @param array $data
     *
     * @return $this|null
     */
    public function updateData(array $data)
    {
        if ( ! empty($data)) {
            $this->order = $data;
        }

        $updated = \App\Models\Back\Orders\Order::where('id', $data['id'])->update([
            'payment_fname'    => $this->order['address']['fname'],
            'payment_lname'    => $this->order['address']['lname'],
            'payment_address'  => $this->order['address']['address'],
            'payment_zip'      => $this->order['address']['zip'],
            'payment_city'     => $this->order['address']['city'],
            'payment_state'    => $this->order['address']['state'],
            'payment_phone'    => $this->order['address']['phone'] ?: null,
            'payment_email'    => $this->order['address']['email'],
            'payment_method'   => $this->order['payment']->title,
            'payment_code'     => $this->order['payment']->code,
            'payment_card'     => '',
            'payment_installment' => '',
            'shipping_fname'   => $this->order['address']['fname'],
            'shipping_lname'   => $this->order['address']['lname'],
            'shipping_address' => $this->order['address']['address'],
            'shipping_zip'     => $this->order['address']['zip'],
            'shipping_city'    => $this->order['address']['city'],
            'shipping_state'   => $this->order['address']['state'],
            'shipping_phone'   => $this->order['address']['phone'] ?: null,
            'shipping_email'   => $this->order['address']['email'],
            'shipping_method'  => $this->order['shipping']->title,
            'shipping_code'    => $this->order['shipping']->code,
            'company'          => $this->order['address']['company'],
            'oib'              => $this->order['address']['oib'],
            'updated_at'       => Carbon::now()
        ]);

        if ($updated) {
            $this->updateProducts($data['id']);
            $this->updateTotal($data['id']);

            return $this->setData($data['id']);
        }

        return null;
    }


    /**
     * @param int $order_id
     *
     * @return bool
     */
    private function updateProducts(int $order_id)
    {
        OrderProduct::where('order_id', $order_id)->delete();

        // PRODUCTS
        foreach ($this->order['cart']['items'] as $item) {
            $discount = 0;
            $price    = $item->price;

            if ($this->checkSpecial($item->associatedModel)) {
                $price    = $item->associatedModel->special;
                $discount = Helper::calculateDiscount($item->price, $price);
            }

            OrderProduct::insert([
                'order_id'   => $order_id,
                'product_id' => $item->id,
                'name'       => $item->name,
                'quantity'   => $item->quantity,
                'org_price'  => $item->price,
                'discount'   => $discount ? number_format($discount, 2) : 0,
                'price'      => $price,
                'total'      => $item->quantity * $price,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
        }

        return true;
    }


    /**
     * @param int $order_id
     */
    private function updateTotal(int $order_id)
    {
        OrderTotal::where('order_id', $order_id)->delete();

        // SUBTOTAL
        OrderTotal::insert([
            'order_id'   => $order_id,
            'code'       => 'subtotal',
            'title'      => 'Ukupno',
            'value'      => $this->order['cart']['subtotal'],
            'sort_order' => 0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        // CONDITIONS on Total
        foreach ($this->order['cart']['conditions'] as $name => $condition) {
            if ($condition->getType() == 'payment') {
                OrderTotal::insert([
                    'order_id'   => $order_id,
                    'code'       => 'payment',
                    'title'      => $name,
                    'value'      => $condition->parsedRawValue,
                    'sort_order' => $condition->getOrder(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }

            if ($condition->getType() == 'shipping') {
                OrderTotal::insert([
                    'order_id'   => $order_id,
                    'code'       => 'shipping',
                    'title'      => $name,
                    'value'      => $condition->parsedRawValue,
                    'sort_order' => $condition->getOrder(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
        }

        // TOTAL
        OrderTotal::insert([
            'order_id'   => $order_id,
            'code'       => 'total',
            'title'      => 'Sveukupno',
            'value'      => $this->order['cart']['total'],
            'sort_order' => 5,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        \App\Models\Back\Orders\Order::where('id', $order_id)->update([
            'total' => $this->order['cart']['total']
        ]);
    }


    /**
     * @param Product $model
     *
     * @return bool
     */
    public function checkSpecial(Product $model): bool
    {
        if ($model->special) {
            $from = now()->subDay();
            $to = now()->addDay();

            if ($model->special_from && $model->special_from != '0000-00-00 00:00:00') {
                $from = Carbon::make($model->special_from);
            }
            if ($model->special_to && $model->special_to != '0000-00-00 00:00:00') {
                $to = Carbon::make($model->special_to);
            }

            if ($from <= now() && now() <= $to) {
                return true;
            }
        }

        return false;
    }


    /**
     * @return mixed|null
     */
    public function resolvePaymentForm()
    {
        if ($this->isCreated()) {
            $method = new PaymentMethod($this->oc_data['payment_code']);

            return $method->resolveForm($this->oc_data);
        }

        return null;
    }


    /**
     * @param Request $request
     *
     * @return mixed|null
     */
    public function finish(Request $request)
    {
        if ($this->isCreated()) {
            $method = new PaymentMethod($this->oc_data['payment_code']);

            return $method->finish($this->oc_data, $request);
        }

        return null;
    }


    /**
     * @return bool
     */
    public function isCreated(): bool
    {
        if ($this->oc_data) {
            return true;
        }

        return false;
    }


    /**
     * @return bool
     */
    public function paymentNotRequired(): bool
    {
        if (in_array($this->oc_data->payment_code, ['cod', 'bank'])) {
            return true;
        }

        return false;
    }
}
