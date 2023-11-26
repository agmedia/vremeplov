<?php

namespace App\Models\Front\Checkout\Payment;

use App\Models\Back\Orders\Order;

/**
 * Class Cod
 * @package App\Models\Front\Checkout\Payment
 */
class Cod
{

    /**
     * @var int
     */
    private $order;


    /**
     * Cod constructor.
     *
     * @param $order
     */
    public function __construct($order)
    {
        $this->order = $order;
    }


    /**
     * @param null $payment_method
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function resolveFormView($payment_method = null)
    {
        $data['order_id'] = $this->order->id;

        return view('front.checkout.payment.cod', compact('data'));
    }


    /**
     * @param Order $order
     * @param null  $request
     *
     * @return bool
     */
    public function finishOrder(Order $order, $request = null): bool
    {
        $updated = $order->update([
            'order_status_id' => config('settings.order.status.new')
        ]);

        if ($updated) {
            return true;
        }

        return false;
    }
}
