<?php

namespace App\Models\Front\Checkout\Payment;

use App\Helpers\Country;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderHistory;
use App\Models\Back\Orders\Transaction;
use App\Models\Back\Settings\Settings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
    public function resolveFormView(?Collection $payment_method = null)
    {
        if ( ! $payment_method) {
            return '';
        }

        $payment_method = $payment_method->first();

        $action = $this->url['live'];

        if ($payment_method->data->test) {
            $action = $this->url['test'];
        }

        $total = number_format($this->order->total, 2, ',', '');
        $_total = str_replace(',', '', $total);

        $shoppingcartid = $this->order->id . '-' . date("Y");

        $hash = hash('sha512', $payment_method->data->shop_id .
            $payment_method->data->secret_key .
            $shoppingcartid .
            $payment_method->data->secret_key .
            $_total .
            $payment_method->data->secret_key
        );

        $data['action'] = $action;
        $data['shop_id'] = $payment_method->data->shop_id;
        $data['order_id'] = $shoppingcartid;
        $data['version'] = '2.0';
        $data['total'] = $total;
        $data['md5'] = $hash;
        $data['firstname'] = $this->order->payment_fname;
        $data['lastname'] = $this->order->payment_lname;
        $data['address'] = $this->order->payment_address;
        $data['city'] = $this->order->payment_city;
        $data['country'] = $this->countryCode($this->order->payment_state);
        $data['postcode'] = $this->order->payment_zip;
        $data['phone'] = $this->order->payment_phone;
        $data['email'] = $this->order->payment_email;
        $data['lang'] = 'HR';
        $data['plan'] = '';
        $data['cc_name'] = '';//...??
        $data['currency'] = 'EUR';
        $data['rate'] = 1;
        $data['return'] = route('checkout');
        $data['error'] = route('checkout');
        $data['cancel'] = route('kosarica');
        $data['method'] = 'POST';

        Log::channel('wspay')->info('WSPay form prepared', [
            'order_id' => $this->order->id,
            'shopping_cart_id' => $shoppingcartid,
            'callback_url' => route('wspay.callback'),
            'mode' => $payment_method->data->test ? 'test' : 'live',
            'action' => $action,
            'amount' => $total,
            'amount_for_signature' => $_total,
            'version' => $data['version'],
            'signature_algorithm' => 'sha512',
            'signature' => $this->signatureMeta($hash),
            'return_url' => $data['return'],
            'return_error_url' => $data['error'],
            'configured_callback_url' => $payment_method->data->callback ?? null,
            'cancel_url' => $data['cancel'],
            'return_method' => 'GET',
            'customer_country_sent' => $data['country'],
        ]);

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
        $success = (string) $request->input('Success') === '1';
        $status = $success ? config('settings.order.status.paid') : config('settings.order.status.declined');
        $signature_valid = $this->validResponseSignature($request);

        Log::channel('wspay')->info('WSPay finish started', [
            'order_id' => $order->id,
            'current_status_id' => $order->order_status_id,
            'target_status_id' => $status,
            'success' => $success,
            'signature_valid' => $signature_valid,
            'response' => $this->responseContext($request),
        ]);

        $updated = $order->update([
            'order_status_id' => $status
        ]);

        Log::channel('wspay')->info('WSPay order status update finished', [
            'order_id' => $order->id,
            'updated' => (bool) $updated,
            'status_id' => $status,
        ]);

        $this->insertTransaction($order, $request, $success);

        if ($success) {
            return true;
        }

        return false;
    }


    /**
     * @param Request $request
     *
     * @return array
     */
    public function handleCallback(Request $request): array
    {
        Log::channel('wspay')->info('WSPay callback received', [
            'callback' => $this->callbackContext($request),
        ]);

        if ($this->validCallbackSignature($request) !== true) {
            Log::channel('wspay')->warning('WSPay callback rejected: invalid signature', [
                'callback' => $this->callbackContext($request),
            ]);

            return [
                'success' => false,
                'message' => 'Invalid signature',
                'http_status' => 403,
            ];
        }

        $order_id = $this->orderIdFromShoppingCartId($request->input('ShoppingCartID'));

        if ( ! $order_id) {
            Log::channel('wspay')->warning('WSPay callback rejected: missing ShoppingCartID', [
                'callback' => $this->callbackContext($request),
            ]);

            return [
                'success' => false,
                'message' => 'Missing ShoppingCartID',
                'http_status' => 422,
            ];
        }

        $result = DB::transaction(function () use ($request, $order_id) {
            $order = Order::query()
                          ->where('id', $order_id)
                          ->lockForUpdate()
                          ->first();

            if ( ! $order) {
                return [
                    'success' => false,
                    'message' => 'Order not found',
                    'order_id' => $order_id,
                    'http_status' => 404,
                ];
            }

            if ($order->payment_code !== 'wspay') {
                return [
                    'success' => false,
                    'message' => 'Order is not a WSPay order',
                    'order_id' => $order->id,
                    'payment_code' => $order->payment_code,
                    'http_status' => 409,
                ];
            }

            $previous_status = (int) $order->order_status_id;
            $target_status = $this->callbackStatus($request, $previous_status);

            $this->insertTransaction($order, $request, $this->flag($request->input('ActionSuccess')));

            if ($target_status && $target_status !== $previous_status) {
                $order->update([
                    'order_status_id' => $target_status,
                    'payment_card' => $request->input('CreditCardName') ?: $order->payment_card,
                    'payment_installment' => $this->installmentCount($request->input('PaymentPlan')),
                ]);

                OrderHistory::insert([
                    'order_id' => $order->id,
                    'user_id' => 0,
                    'status' => $target_status,
                    'comment' => 'WSPay callback status: ' . $this->callbackStatusLabel($request),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            return [
                'success' => true,
                'message' => 'Accepted',
                'order_id' => $order->id,
                'previous_status_id' => $previous_status,
                'status_id' => $target_status ?: $previous_status,
                'changed' => (bool) ($target_status && $target_status !== $previous_status),
                'http_status' => 200,
            ];
        });

        Log::channel('wspay')->info('WSPay callback processed', array_merge($result, [
            'callback' => $this->callbackContext($request),
        ]));

        return $result;
    }


    /**
     * @param Order   $order
     * @param Request $request
     * @param bool    $success
     */
    private function insertTransaction(Order $order, Request $request, bool $success): void
    {
        try {
            Transaction::insert([
                'order_id' => $order->id,
                'success' => $success ? 1 : 0,
                'amount' => $this->decimalAmount($request->input('Amount')),
                'signature' => (string) $request->input('Signature', ''),
                'payment_type' => $request->input('PaymentType') ?: $request->input('CreditCardName'),
                'payment_plan' => $request->input('PaymentPlan'),
                'payment_partner' => $success ? $request->input('Partner') : null,
                'datetime' => $this->transactionDateTime($request->input('DateTime') ?: $request->input('TransactionDateTime')),
                'approval_code' => $request->input('ApprovalCode'),
                'pg_order_id' => $request->input('WsPayOrderId'),
                'lang' => (string) $request->input('Lang', ''),
                'stan' => $request->input('STAN'),
                'error' => $request->input('ErrorMessage'),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            Log::channel('wspay')->info('WSPay transaction row inserted', [
                'order_id' => $order->id,
                'success' => $success,
            ]);
        } catch (\Throwable $exception) {
            Log::channel('wspay')->error('WSPay transaction row insert failed', [
                'order_id' => $order->id,
                'success' => $success,
                'error' => $exception->getMessage(),
            ]);
        }
    }


    /**
     * @param Request $request
     *
     * @return bool|null
     */
    private function validResponseSignature(Request $request): ?bool
    {
        $payment_method = Settings::get('payment', 'list.wspay')->first();

        if ( ! $payment_method || ! isset($payment_method->data->shop_id, $payment_method->data->secret_key)) {
            return null;
        }

        $signature = (string) $request->input('Signature', '');

        if ($signature === '') {
            return false;
        }

        $expected = hash('sha512', $payment_method->data->shop_id .
            $payment_method->data->secret_key .
            (string) $request->input('ShoppingCartID', '') .
            $payment_method->data->secret_key .
            (string) $request->input('Success', '') .
            $payment_method->data->secret_key .
            (string) $request->input('ApprovalCode', '') .
            $payment_method->data->secret_key
        );

        return hash_equals(strtolower($expected), strtolower($signature));
    }


    /**
     * @param Request $request
     *
     * @return bool|null
     */
    private function validCallbackSignature(Request $request): ?bool
    {
        $payment_method = Settings::get('payment', 'list.wspay')->first();

        if ( ! $payment_method || ! isset($payment_method->data->shop_id, $payment_method->data->secret_key)) {
            return null;
        }

        $signature = (string) $request->input('Signature', '');

        if ($signature === '') {
            return false;
        }

        $expected = hash('sha512', $payment_method->data->shop_id .
            $payment_method->data->secret_key .
            (string) $request->input('ActionSuccess', '') .
            (string) $request->input('ApprovalCode', '') .
            $payment_method->data->secret_key .
            $payment_method->data->shop_id .
            (string) $request->input('ApprovalCode', '') .
            (string) $request->input('WsPayOrderId', '')
        );

        return hash_equals(strtolower($expected), strtolower($signature));
    }


    /**
     * @param Request $request
     *
     * @return array
     */
    private function responseContext(Request $request): array
    {
        return [
            'method' => $request->method(),
            'shopping_cart_id' => $request->input('ShoppingCartID'),
            'success' => $request->input('Success'),
            'approval_code_present' => filled($request->input('ApprovalCode')),
            'amount' => $request->input('Amount'),
            'wspay_order_id' => $request->input('WsPayOrderId'),
            'stan' => $request->input('STAN'),
            'payment_type' => $request->input('PaymentType'),
            'payment_plan' => $request->input('PaymentPlan'),
            'partner' => $request->input('Partner'),
            'datetime' => $request->input('DateTime'),
            'lang' => $request->input('Lang'),
            'response_code' => $request->input('ResponseCode'),
            'error_message' => $request->input('ErrorMessage'),
            'error_codes' => $request->input('ErrorCodes'),
            'signature' => $this->signatureMeta($request->input('Signature')),
            'received_keys' => array_keys($request->all()),
        ];
    }


    /**
     * @param Request $request
     *
     * @return array
     */
    private function callbackContext(Request $request): array
    {
        return [
            'method' => $request->method(),
            'shopping_cart_id' => $request->input('ShoppingCartID'),
            'order_id' => $this->orderIdFromShoppingCartId($request->input('ShoppingCartID')),
            'shop_id' => $request->input('ShopID'),
            'action_success' => $request->input('ActionSuccess'),
            'authorized' => $request->input('Authorized'),
            'completed' => $request->input('Completed'),
            'voided' => $request->input('Voided'),
            'refunded' => $request->input('Refunded'),
            'approval_code_present' => filled($request->input('ApprovalCode')),
            'amount' => $request->input('Amount'),
            'currency_code' => $request->input('CurrencyCode'),
            'wspay_order_id' => $request->input('WsPayOrderId'),
            'unique_transaction_number' => $request->input('UniqueTransactionNumber'),
            'stan' => $request->input('STAN'),
            'payment_plan' => $request->input('PaymentPlan'),
            'partner' => $request->input('Partner'),
            'credit_card_name' => $request->input('CreditCardName'),
            'transaction_datetime' => $request->input('TransactionDateTime'),
            'can_be_completed' => $request->input('CanBeCompleted'),
            'can_be_voided' => $request->input('CanBeVoided'),
            'can_be_refunded' => $request->input('CanBeRefunded'),
            'signature' => $this->signatureMeta($request->input('Signature')),
            'received_keys' => array_keys($request->all()),
        ];
    }


    /**
     * @param Request $request
     * @param int     $current_status
     *
     * @return int|null
     */
    private function callbackStatus(Request $request, int $current_status): ?int
    {
        if ($this->flag($request->input('Refunded'))) {
            return config('settings.order.status.returned');
        }

        if ($this->flag($request->input('Voided'))) {
            return config('settings.order.status.declined');
        }

        if ($this->flag($request->input('ActionSuccess')) &&
            ($this->flag($request->input('Completed')) || $this->flag($request->input('Authorized')))) {
            return config('settings.order.status.paid');
        }

        if ( ! $this->flag($request->input('ActionSuccess')) &&
            $current_status === config('settings.order.status.unfinished')) {
            return config('settings.order.status.declined');
        }

        return null;
    }


    /**
     * @param Request $request
     *
     * @return string
     */
    private function callbackStatusLabel(Request $request): string
    {
        $parts = [];

        foreach (['ActionSuccess', 'Authorized', 'Completed', 'Voided', 'Refunded'] as $key) {
            if ($request->has($key)) {
                $parts[] = $key . '=' . $request->input($key);
            }
        }

        if ($request->input('WsPayOrderId')) {
            $parts[] = 'WsPayOrderId=' . $request->input('WsPayOrderId');
        }

        return implode(', ', $parts);
    }


    /**
     * @param mixed $value
     *
     * @return bool
     */
    private function flag($value): bool
    {
        return in_array($value, [1, '1', true, 'true'], true);
    }


    /**
     * @param string|null $shopping_cart_id
     *
     * @return string
     */
    private function orderIdFromShoppingCartId(?string $shopping_cart_id): string
    {
        return preg_replace('/-\d{4}$/', '', (string) $shopping_cart_id);
    }


    /**
     * @param string|null $payment_plan
     *
     * @return int
     */
    private function installmentCount(?string $payment_plan): int
    {
        if ( ! $payment_plan) {
            return 0;
        }

        $count = (int) substr($payment_plan, 0, 2);

        return max(0, $count);
    }


    /**
     * @param string|null $signature
     *
     * @return array
     */
    private function signatureMeta(?string $signature): array
    {
        if ( ! $signature) {
            return [
                'present' => false,
                'length' => 0,
                'preview' => null,
            ];
        }

        return [
            'present' => true,
            'length' => strlen($signature),
            'preview' => substr($signature, 0, 8) . '...' . substr($signature, -8),
        ];
    }


    /**
     * @param string|null $amount
     *
     * @return string
     */
    private function decimalAmount(?string $amount): string
    {
        if ( ! $amount) {
            return '0.00';
        }

        return str_replace(',', '.', str_replace('.', '', $amount));
    }


    /**
     * @param string|null $date_time
     *
     * @return Carbon
     */
    private function transactionDateTime(?string $date_time): Carbon
    {
        if ( ! $date_time) {
            return Carbon::now();
        }

        try {
            if (preg_match('/^\d{14}$/', $date_time)) {
                return Carbon::createFromFormat('YmdHis', $date_time);
            }

            return Carbon::parse($date_time);
        } catch (\Throwable $exception) {
            return Carbon::now();
        }
    }


    /**
     * @param string|null $country
     *
     * @return string
     */
    private function countryCode(?string $country): string
    {
        $country = trim((string) $country);

        if (preg_match('/^[A-Z]{2}$/', $country)) {
            return $country;
        }

        try {
            $match = Country::list()->firstWhere('name', $country);

            if ($match && isset($match['iso_code_2'])) {
                return $match['iso_code_2'];
            }
        } catch (\Throwable $exception) {
            Log::channel('wspay')->warning('WSPay country code lookup failed', [
                'country' => $country,
                'error' => $exception->getMessage(),
            ]);
        }

        return $country ?: 'HR';
    }

}
