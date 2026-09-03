<?php

namespace App\Models\Front\Checkout;

use App\Helpers\Helper;
use App\Models\Back\Orders\OrderHistory;
use App\Models\Back\Orders\OrderProduct;
use App\Models\Back\Orders\OrderTotal;
use App\Models\Back\Settings\Settings;
use App\Models\Front\Catalog\Product;
use App\Services\Inventory\OrderInventoryService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return $statuses->where('id', $id)->first() ?: (object) [
            'id'    => $id,
            'title' => 'Nepoznat status',
            'name'  => 'Nepoznat status',
            'color' => 'light',
        ];
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
        $data = \App\Models\Back\Orders\Order::query()->where('id', $id)->first();

        if ($data) {
            $this->oc_data = $data;

            if ($data->payment_code == 'wspay') {
                Log::channel('wspay')->info('Order loaded for WSPay return', [
                    'order_id' => $data->id,
                    'order_status_id' => $data->order_status_id,
                    'payment_code' => $data->payment_code,
                    'total' => $data->total,
                ]);
            }

            return $this;
        }

        Log::channel('wspay')->warning('Order lookup failed during checkout return', [
            'order_id' => $id,
        ]);

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
                'comment'          => $this->order['comment'],
                'commentp'          => $this->order['commentp'],
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

        $orderQuery = \App\Models\Back\Orders\Order::where('id', $data['id']);

        if (! $orderQuery->exists()) {
            return null;
        }

        $orderQuery->update([
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
            'comment'          => $this->order['comment'],
            'commentp'          => $this->order['commentp'],
            'updated_at'       => Carbon::now()
        ]);

        // MySQL may report zero changed rows when the address/payment data is
        // identical. Cart lines and totals can still have changed, so they must
        // always be refreshed for an existing unfinished checkout order.
        $this->updateProducts($data['id']);
        $this->updateTotal($data['id']);

        return $this->setData($data['id']);
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

            if ($condition->getType() == 'special') {
                OrderTotal::insert([
                    'order_id'   => $order_id,
                    'code'       => 'special',
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
    public function finish(Request $request, string $expectedPaymentCode)
    {
        if ($this->isCreated()) {
            return DB::transaction(function () use ($request, $expectedPaymentCode) {
                $currentOrder = \App\Models\Back\Orders\Order::query()
                    ->where('id', $this->oc_data['id'])
                    ->lockForUpdate()
                    ->first();

                if (! $currentOrder) {
                    return false;
                }

                $paymentCode = strtolower((string) $currentOrder->payment_code);

                if (! hash_equals($paymentCode, strtolower($expectedPaymentCode))) {
                    Log::warning('Checkout payment provider mismatch rejected.', [
                        'order_id' => $currentOrder->id,
                        'expected_payment_code' => strtolower($expectedPaymentCode),
                        'actual_payment_code' => $paymentCode,
                    ]);

                    return false;
                }

                $method = new PaymentMethod($paymentCode);
                $previousStatus = (int) $currentOrder->order_status_id;

                if (in_array($paymentCode, ['cod', 'bank', 'pickup'], true)
                    && $previousStatus !== (int) config('settings.order.status.unfinished')) {
                    return false;
                }

                if ($paymentCode === 'wspay') {
                    Log::channel('wspay')->info('Order finish resolving WSPay provider', [
                        'order_id' => $currentOrder->id,
                        'order_status_id' => $previousStatus,
                        'request_keys' => array_keys($request->all()),
                    ]);
                }

                $result = $method->finish($currentOrder, $request);
                $currentOrder->refresh();

                try {
                    app(OrderInventoryService::class)->applyStatusTransition(
                        $currentOrder,
                        $previousStatus,
                        (int) $currentOrder->order_status_id,
                        'payment_return:' . $paymentCode
                    );
                } catch (\Throwable $exception) {
                    if (! $this->isExternallyPaid($paymentCode, $currentOrder)) {
                        throw $exception;
                    }

                    app(OrderInventoryService::class)->recordAllocationError($currentOrder, $exception);

                    $manualStatus = (int) config('settings.order.status.call_when_found');
                    $currentOrder->update(['order_status_id' => $manualStatus]);

                    OrderHistory::insert([
                        'order_id' => $currentOrder->id,
                        'user_id' => 0,
                        'status' => $manualStatus,
                        'comment' => 'Plaćanje je potvrđeno, ali zalihu nije bilo moguće rezervirati: ' .
                            mb_substr($exception->getMessage(), 0, 400),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);

                    Log::critical('Plaćena narudžba zahtijeva ručnu provjeru zalihe.', [
                        'order_id' => $currentOrder->id,
                        'payment_code' => $paymentCode,
                        'status_id' => $manualStatus,
                    ]);
                }

                $this->oc_data = $currentOrder->fresh();

                if ($paymentCode === 'wspay') {
                    Log::channel('wspay')->info('Order finish completed for WSPay provider', [
                        'order_id' => $currentOrder->id,
                        'result' => (bool) $result,
                    ]);
                }

                return $result;
            });
        }

        Log::channel('wspay')->warning('Order finish skipped because order data was not loaded', [
            'shopping_cart_id' => $request->input('ShoppingCartID'),
            'success' => $request->input('Success'),
            'request_keys' => array_keys($request->all()),
        ]);

        return null;
    }


    private function isExternallyPaid(string $paymentCode, \App\Models\Back\Orders\Order $order): bool
    {
        return in_array($paymentCode, ['wspay', 'paypal', 'corvus', 'payway', 'keks'], true)
            && in_array((int) $order->order_status_id, [
                (int) config('settings.order.status.paid'),
                (int) config('settings.order.status.new'),
            ], true);
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
