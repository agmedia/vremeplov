<?php

namespace App\Models\Front\Checkout\Payment;

use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class Payway
 * @package App\Models\Front\Checkout\Payment
 */
class PayPalStandard
{

    /**
     * @var Order
     */
    private $order;

    /**
     * @var string[]
     */
    private $url = [
        'test' => 'https://www.sandbox.paypal.com/cgi-bin/webscr&pal=V4T754QB63XXL',
        'live' => 'https://www.paypal.com/cgi-bin/webscr&pal=V4T754QB63XXL'
    ];


    /**
     * Payway constructor.
     *
     * @param Order $order
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }


    /**
     * @param Collection|null $payment_method
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function resolveFormView(Collection $payment_method = null)
    {
        if ( ! $payment_method) {
            return '';
        }

        $payment_method = $payment_method->first();

        $action = $this->url['live'];
        $data['testmode'] = false;
        $data['business'] = 'info@antiqueshop.hr';

        if ($payment_method->data->test) {
            $action = $this->url['test'];
            $data['testmode'] = true;
            $data['business'] = 'tomislav-facilitator@agmedia.hr';
        }

        $data['action'] = $action;
        $data['order_id'] = $this->order->id;

        foreach ($this->order->products()->get() as $item) {
            $data['products'][] = [
                'name'     => htmlspecialchars($item->name),
                'model'    => htmlspecialchars($item->product()->first()->sku),
                'price'    => number_format($item->price,2),
                'quantity' => $item->quantity
            ];
        }

        $subtotal = $this->order->total;

        foreach ($this->order->totals()->get() as $item) {
            if ($item->code == 'subtotal') {
                $subtotal = $item->value;
            }
        }

        $data['discount_amount_cart'] = 0;

        $total = $this->order->total - $subtotal;

        if ($total > 0) {
            $data['products'][] = array(
                'name'     => 'Discount item',
                'model'    => '',
                'price'    => number_format($total, 2),
                'quantity' => 1
            );
        } else {
            $data['discount_amount_cart'] -= number_format($total, 2);
        }

        $data['currency'] = 'EUR';
        $data['firstname'] = $this->order->payment_fname;
        $data['lastname'] = $this->order->payment_lname;
        $data['address'] = $this->order->payment_address;
        $data['city'] = $this->order->payment_city;
        $data['country'] = $this->order->payment_state;
        $data['postcode'] = $this->order->payment_zip;
        $data['phone'] = $this->order->payment_phone;
        $data['email'] = $this->order->payment_email;
        $data['invoice'] = $this->order->id . ' - ' . $this->order->payment_fname . ' ' . $this->order->payment_lname;
        $data['lc'] = 'HR';
        $data['return'] = url($payment_method->data->callback);
        $data['notify_url'] = url($payment_method->data->callback);
        $data['cancel_return'] = route('kosarica');

        return view('front.checkout.payment.paypal_standard', compact('data'));
    }


    /**
     * @param Order $order
     * @param null  $request
     *
     * @return bool
     */
    public function finishOrder(Order $order, Request $request): bool
    {
        $status = $request->input('Success') ? config('settings.order.status.paid') : config('settings.order.status.declined');

        $order->update([
            'order_status_id' => $status
        ]);

        if ($request->input('Success')) {
            Transaction::insert([
                'order_id' => $order->id,
                'success' => 1,
                'amount' => $request->input('Amount'),
                'signature' => $request->input('Signature'),
                'payment_type' => $request->input('PaymentType'),
                'payment_plan' => $request->input('PaymentPlan'),
                'payment_partner' => $request->input('Partner'),
                'datetime' => $request->input('DateTime'),
                'approval_code' => $request->input('ApprovalCode'),
                'pg_order_id' => $request->input('WsPayOrderId'),
                'lang' => $request->input('Lang'),
                'stan' => $request->input('STAN'),
                'error' => $request->input('ErrorMessage'),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            return true;
        }

        Transaction::insert([
            'order_id' => $order->id,
            'success' => 0,
            'amount' => $request->input('Amount'),
            'signature' => $request->input('Signature'),
            'payment_type' => $request->input('PaymentType'),
            'payment_plan' => $request->input('PaymentPlan'),
            'payment_partner' => null,
            'datetime' => $request->input('DateTime'),
            'approval_code' => $request->input('ApprovalCode'),
            'pg_order_id' => null,
            'lang' => $request->input('Lang'),
            'stan' => null,
            'error' => $request->input('ErrorMessage'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        return false;
    }

}
