<?php

namespace App\Models\Front\Checkout\Payment;

use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\Transaction;
use App\Models\Back\Settings\Settings;
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

        $order_id = $this->order->id;

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
        $data['order_id'] = $order_id;

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
        $data['invoice'] = $order_id . ' - ' . $this->order->payment_fname . ' ' . $this->order->payment_lname;
        $data['lc'] = 'HR';
        $data['return'] = url($payment_method->data->callback);
        $data['rm'] = 2;
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
        /*$curl_request = 'cmd=_notify-validate';

        foreach ($request->toArray() as $key => $value) {
            $curl_request .= '&' . $key . '=' . rawurlencode(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
        }

        $paypal_settings = Settings::get('payment', 'list.paypal');
        $curl = curl_init('https://www.sandbox.paypal.com/cgi-bin/webscr');

        if ($paypal_settings && ! $paypal_settings->first()->data->test) {
            $curl = curl_init('https://ipnpb.paypal.com/cgi-bin/webscr');
        }

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $curl_request);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($curl);*/

        $order_status_id = 0;

        switch($request->input('payment_status')) {
            case 'Canceled_Reversal':
                $order_status_id = config('settings.order.status.canceled');
                break;
            case 'Completed':
                $order_status_id = config('settings.order.status.paid');
                break;
            case 'Denied':
                $order_status_id = config('settings.order.status.declined');
                break;
            case 'Expired':
                $order_status_id = config('settings.order.status.declined');
                break;
            case 'Failed':
                $order_status_id = config('settings.order.status.unfinished');
                break;
            case 'Pending':
                $order_status_id = config('settings.order.status.new');
                break;
            case 'Processed':
                $order_status_id = config('settings.order.status.new');
                break;
            case 'Refunded':
                $order_status_id = config('settings.order.status.returned');
                break;
            case 'Reversed':
                $order_status_id = config('settings.order.status.returned');
                break;
            case 'Voided':
                $order_status_id = config('settings.order.status.declined');
                break;
        }

        $order->update([
            'order_status_id' => $order_status_id
        ]);

        Transaction::insert([
            'order_id' => $order->id,
            'success' => 1,
            'amount' => $order->total,
            'signature' => $request->input('PayerID') ?? '',
            'payment_type' => $request->input('payment_type') ?? '',
            'payment_plan' => '',
            'payment_partner' => 'Paypal',
            'datetime' => $request->input('payment_date') ?? now(),
            'approval_code' => $request->input('verify_sign') ?? '',
            'pg_order_id' => $request->input('PayerID'),
            'lang' => 'hr',
            'stan' => $request->input('invoice'),
            'error' => '',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        Log::info($order->id . '_ Order status ID = ' . $order_status_id);

        if ($order_status_id == config('settings.order.status.paid')) {
            return true;
        }

        return false;
    }

}
