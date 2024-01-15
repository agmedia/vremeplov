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
class WSpay
{

    /**
     * @var Order
     */
    private $order;

    /**
     * @var string[]
     */
    private $url = [
        'test' => 'https://formtest.wspay.biz/Authorization.aspx',
        'live' => 'https://form.wspay.biz/Authorization.aspx'
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

        if ($payment_method->data->test) {
            $action = $this->url['test'];
        }

        $total = number_format($this->order->total,2, ',', '');
        $_total = str_replace( ',', '', $total);

        $shoppingcartid = $this->order->id;

        // $stringhash = $payment_method->data->shop_id.$payment_method->data->secret_key.$shoppingcartid.$payment_method->data->secret_key.$_total.$payment_method->data->secret_key;



        // $hash = hash('sha512', $stringhash);

        $hash = md5($payment_method->data->shop_id .
            $payment_method->data->secret_key .
            $this->order->id.'-'.date("Y") .
            $payment_method->data->secret_key .
            $_total.
            $payment_method->data->secret_key
        );

        $data['action'] = $action;
        $data['shop_id'] = $payment_method->data->shop_id;
        $data['order_id'] = $this->order->id.'-'.date("Y");
        $data['total'] = $total;
        $data['md5'] = $hash;
        $data['firstname'] = $this->order->payment_fname;
        $data['lastname'] = $this->order->payment_lname;
        $data['address'] = $this->order->payment_address;
        $data['city'] = $this->order->payment_city;
        $data['country'] = $this->order->payment_state;
        $data['postcode'] = $this->order->payment_zip;
        $data['phone'] = $this->order->payment_phone;
        $data['email'] = $this->order->payment_email;
        $data['lang'] = 'HR';
        $data['plan'] = '';
        $data['cc_name'] = '';//...??
        $data['currency'] = 'EUR';
        $data['rate'] = 1;
        $data['return'] = $payment_method->data->callback;
        $data['cancel'] = route('kosarica');
        $data['method'] = 'POST';

        return view('front.checkout.payment.wspay', compact('data'));
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
               /* 'amount' => $request->input('Amount'),
                'signature' => $request->input('Signature'),
                'payment_type' => $request->input('PaymentType'),
                'payment_plan' => $request->input('PaymentPlan'),
                'payment_partner' => $request->input('Partner'),
                'datetime' => $request->input('DateTime'),
                'approval_code' => $request->input('ApprovalCode'),
                'pg_order_id' => $request->input('WsPayOrderId'),
                'lang' => $request->input('Lang'),
                'stan' => $request->input('STAN'),
                'error' => $request->input('ErrorMessage'),*/
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            return true;
        }

        Transaction::insert([
            'order_id' => $order->id,
            'success' => 0,
           /* 'amount' => $request->input('Amount'),
            'signature' => $request->input('Signature'),
            'payment_type' => $request->input('PaymentType'),
            'payment_plan' => $request->input('PaymentPlan'),
            'payment_partner' => null,
            'datetime' => $request->input('DateTime'),
            'approval_code' => $request->input('ApprovalCode'),
            'pg_order_id' => null,
            'lang' => $request->input('Lang'),
            'stan' => null,
            'error' => $request->input('ErrorMessage'),*/
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        return false;
    }

}
