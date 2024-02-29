<?php

namespace App\Models\Front\Checkout\Payment;

use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Srmklive\PayPal\Services\PayPal as PaypalClient;

/**
 * Class Payway
 * @package App\Models\Front\Checkout\Payment
 */
class PayPal
{

    /**
     * @var Order
     */
    private $order;

    /**
     * @var array
     */
    private $config = [];

    /**
     * @var string
     */
    private $client_id = '';

    /**
     * @var string
     */
    private $currency = '';


    /**
     * Payway constructor.
     *
     * @param Order $order
     */
    public function __construct(Order $order)
    {
        $this->order    = $order;
        $this->currency = 'EUR';
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

        $this->setPaypalConfig($payment_method->first());

        $paypal = new PaypalClient();
        $paypal->setApiCredentials($this->config);
        $paypal->setAccessToken($paypal->getAccessToken());

        Log::info('$paypal->setApiCredentials(;:::::::::::$this->config)::');
        Log::info($this->config);

        $order = $paypal->createOrder([
            "intent"         => "CAPTURE",
            "purchase_units" => [
                [
                    "amount"      => [
                        "currency_code" => $this->currency,
                        "value"         => number_format($this->order->total, 2)
                    ],
                    'description' => 'test'
                ]
            ],
        ]);

        $data = [];

        Log::info('resolveFormView:::::::::::$order::');
        Log::info($order);

        if (isset($order['status']) && $order['status'] == 'CREATED') {
            $data['vendor_id']   = $order['id'];
            $data['currency']    = $this->currency;
            $data['client_id']   = $this->client_id;
            $data['approve_url'] = route('checkout');
            $data['error_url']   = route('checkout.error');
            $data['status']      = $order['status'];

            $this->order->update([
                'tracking_code' => $order['id']
            ]);
        }

        return view('front.checkout.payment.paypal', compact('data'));
    }


    /**
     * @param Order $order
     * @param null  $request
     *
     * @return bool
     */
    public function finishOrder(Order $order, Request $request): bool
    {
        Log::info('finishOrder:::::::::::$request::');
        Log::info($request->toArray());

        $status = config('settings.order.status.paid');

        $order->update([
            'order_status_id' => $status,
            'tracking_code'   => ''
        ]);

        Transaction::insert([
            'order_id'      => $order->id,
            'success'       => 0,
            'signature'     => $request->input('signature'),
            //'approval_code' => $request->input('approval_code'),
            'pg_order_id'   => $request->input('provjera'),
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now()
        ]);

        return false;
    }


    /**
     * @param $paypal_data
     *
     * @return array
     */
    private function setPaypalConfig($paypal_data): array
    {
        if (isset($paypal_data->code) && $paypal_data->code == 'paypal') {
            $is_test = $paypal_data->data->test;

            $this->config = [
                'mode'           => $is_test ? 'sandbox' : 'live',
                'payment_action' => env('PAYPAL_PAYMENT_ACTION', 'Sale'), // Can only be 'Sale', 'Authorization' or 'Order'
                'currency'       => env('PAYPAL_CURRENCY', $this->currency),
                'notify_url'     => env('PAYPAL_NOTIFY_URL', url($paypal_data->data->callback)), // Change this accordingly for your application.
                'locale'         => env('PAYPAL_LOCALE', 'en_US'), // force gateway language  i.e. it_IT, es_ES, en_US ... (for express checkout only)
                'validate_ssl'   => env('PAYPAL_VALIDATE_SSL', $is_test ? false : true),
            ];

            if ($is_test) {
                $this->client_id = $paypal_data->data->test_id;

                $this->config['sandbox'] = [
                    'client_id'     => $this->client_id,
                    'client_secret' => $paypal_data->data->test_secret,
                    'app_id'        => 'APP-80W284485P519543T',
                ];
            } else {
                $this->client_id = $paypal_data->data->live_id;

                $this->config['live'] = [
                    'client_id'     => $this->client_id,
                    'client_secret' => $paypal_data->data->live_secret,
                ];

                $paypal = new PaypalClient($this->config);
                $app_id = $paypal->getAccessToken();

                if (isset($app_id['app_id'])) {
                    $this->config['live']['app_id'] = $app_id['app_id'];
                }
            }

            return $this->config;
        }

        return $this->config;
    }

}
