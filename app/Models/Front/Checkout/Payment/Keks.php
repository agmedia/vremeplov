<?php

namespace App\Models\Front\Checkout\Payment;

require_once app_path('Helpers/Kekspay/autoload.php');

use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\Transaction;
use Carbon\Carbon;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Class Keks
 * @package App\Models\Front\Checkout\Payment
 */
class Keks
{

    /**
     * @var Order
     */
    private $order;

    /**
     * @var string
     */
    private $env;


    /**
     * @var string[]
     */
    private $url = [
        'test' => [
            'action' => 'https://kekspayuat.erstebank.hr/eretailer',
            'deep_link' => 'https://kekspay.hr/galebpay'
        ],
        'live' => [
            'action' => 'https://ewa.erstebank.hr/eretailer',
            'deep_link' => 'https://kekspay.hr/pay'
        ]
    ];


    /**
     * Keks constructor.
     *
     * @param $order
     */
    public function __construct($order = null)
    {
        $this->order = $order;
        $this->env   = (env('APP_ENV') == 'production') ? 'live' : 'test';
    }


    /**
     * @param Collection|null $payment_method
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function resolveFormView(Collection $payment_method = null, array $options = null)
    {
        if ( ! $payment_method) {
            return '';
        }

        $payment_method = $payment_method->first();

        $order_id   = isset($options['order_number']) ? $options['order_number'] : $this->order->id;
        $total      = number_format(isset($options['total']) ? $options['total'] : $this->order->total, 2, '.', '');
        $store_name = rawurlencode(env('APP_NAME'));

        $data['action']      = $this->url[$this->env]['action'];
        $data['deep_link']   = $this->url[$this->env]['deep_link'];
        $data['logo']        = asset('media/img/keks-logo.svg');
        $data['success_url'] = route('checkout.success');
        $data['fail_url']    = route('checkout.error');

        $data['qr_code']     = 1;
        $data['cid']         = $payment_method->data->cid;
        $data['tid']         = $payment_method->data->tid;
        $data['bill_id']     = $payment_method->data->cid . time() .  '-' . $order_id;
        $data['amount']      = $total;
        $data['store']       = $store_name;
        $data['order_id']    = $order_id;

        $qr_options = new QROptions([
            'version'          => 6,
            'quietzoneSize'    => 4,
            'eccLevel'         => QRCode::ECC_L,
            'imageTransparent' => false,
        ]);

        $qr_data = [
            "qr_type" => 1,
            "cid"     => $payment_method->data->cid,
            "tid"     => $payment_method->data->tid,
            "bill_id" => $payment_method->data->cid . time() . $order_id,
            "amount"  => $total,
            "store"   => $store_name,
        ];

        $qr_code = new QRCode($qr_options);

        $data['qr_img'] = $qr_code->render(json_encode($qr_data));

        return view('front.checkout.payment.keks', compact('data'));
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(Request $request)
    {
        $request->validate([
            'order_id' => 'required'
        ]);

        $json['status'] = 0;

        $this->order = Order::query()->find($request->input('order_id'));

        if ($this->order) {
            if ($this->order['order_status_id'] != config('settings.order.status.unfinished')) {
                $json['redirect'] = route('kosarica');
                $json['status']   = 1;

                if ($this->order['order_status_id'] == config('settings.order.new_status')) {
                    $json['redirect'] = route('checkout.success');
                }
            }
        }

        return response()->json($json);
    }


    /**
     * @param Order   $order
     * @param Request $request
     *
     * @return bool
     */
    public function finishOrder(Order $order, Request $request): bool
    {
        $order->update([
            'order_status_id' => config('settings.order.new_status')
        ]);

        Transaction::insert([
            'order_id'        => $order->id,
            'success'         => 1,
            'amount'          => $request->input('amount'),
            'signature'       => $request->header('Authorization'),
            /*'payment_type'    => $request->input('PaymentType'),
            'payment_plan'    => $request->input('PaymentPlan'),
            'payment_partner' => $request->input('Partner'),*/
            'datetime'        => $order->created_at,
            'approval_code'   => $request->input('bill_id'),
            'pg_order_id'     => $request->input('keks_id'),
            'lang'            => 'hr',
            /*'stan'            => $request->input('STAN'),
            'error'           => $request->input('ErrorMessage'),*/
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now()
        ]);

        return true;
    }

}
