<?php

namespace App\Models\Front\Checkout\Payment;

use App\Models\Back\Orders\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Class Bank
 * @package App\Models\Front\Checkout\Payment
 */
class Bank
{

    /**
     * @var int
     */
    private $order;

    /**
     * @var string
     */
    private $barcode_url = 'https://hub3.bigfish.software/api/v2/barcode';


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

        $pozivnabroj = $this->order->id . '-' . date("ym");

        $total = str_replace(',', '', number_format($this->order->total, 2, ',', ''));

        $data['firstname'] = $this->order->payment_fname;
        $data['lastname']  = $this->order->payment_lname;
        $data['address']   = $this->order->payment_address;
        $data['city']      = $this->order->payment_city;
        $data['country']   = $this->order->payment_state;
        $data['postcode']  = $this->order->payment_zip;
        $data['phone']     = $this->order->payment_phone;
        $data['email']     = $this->order->payment_email;

        $hubstring = array(
            'renderer' => 'image',
            'options'  =>
                array(
                    "format"  => "jpg",
                    "padding" => 20,
                    "color"   => "#2c3e50",
                    "bgColor" => "#fff",
                    "scale"   => 3,
                    "ratio"   => 3
                ),
            'data'     =>
                array(
                    'amount'      => (int)$total,
                    'currency'    => 'EUR',
                    'sender'      =>
                        array(
                            'name'   => $data['firstname'] . ' ' . $data['lastname'],
                            'street' => $data['address'],
                            'place'  => $data['postcode'] . ' ' . $data['city'],
                        ),
                    'receiver'    =>
                        array(
                            'name'      => 'Plava krava izdavaštvo',
                            'street'    => 'Nova cesta 130',
                            'place'     => '10000 Zagreb',
                            'iban'      => 'HR9824020061101122961',
                            'model'     => '00',
                            'reference' => $pozivnabroj,
                        ),
                    'purpose'     => 'CMDT',
                    'description' => 'Web narudžba Plava Krava',
                ),
        );

        $ch = curl_init($this->barcode_url);

        # Setting our options
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($hubstring));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        # Get the response

        $response = curl_exec($ch);
        curl_close($ch);

        $response = base64_encode($response);

        $data['uplatnica'] = $response;
        $scimg             = 'data:image/png;base64,' . $response;
        list($type, $scimg) = explode(';', $scimg);
        list(, $scimg) = explode(',', $scimg);
        $scimg = base64_decode($scimg);

        $path = $this->order->id . '.jpg';

        Storage::disk('qr')->put($path, $scimg);

        return view('front.checkout.payment.bank', compact('data'));
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
