<?php

namespace App\Http\Controllers\Front;

use App\Helpers\Session\CheckoutSession;
use App\Http\Controllers\Controller;
use App\Mail\OrderReceived;
use App\Mail\OrderSent;
use App\Models\Back\Orders\Order as BackOrder;
use App\Models\Back\Settings\Settings;
use App\Models\Front\AgCart;
use App\Models\Front\Checkout\GeoZone;
use App\Models\Front\Checkout\Order;
use App\Models\Front\Checkout\PaymentMethod;
use App\Models\Front\Checkout\ShippingMethod;
use App\Models\Front\Checkout\Payment\Wspay;
use App\Models\Front\Checkout\Shipping\Gls;
use App\Models\TagManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use SoapClient;
use \stdClass;

class CheckoutController extends Controller
{

    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function cart(Request $request)
    {
        $gdl = TagManager::getGoogleCartDataLayer($this->shoppingCart()->get());

        return view('front.checkout.cart', compact('gdl'));
    }


    /**
     * @param Request $request
     * @param string  $step
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function checkout(Request $request)
    {
        $step = '';

        if ($request->has('step')) {
            $step = $request->input('step');
        }

        $cart = $this->shoppingCart()->get();
        if (empty($cart['count']) || (int) $cart['count'] <= 0) {
            return redirect()->route('kosarica');
        }

        $is_free_shipping = (config('settings.free_shipping') < $this->shoppingCart()->get()['subtotal']) ? true : false;

        return view('front.checkout.checkout', compact('step', 'is_free_shipping'));
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function view(Request $request)
    {
        $cart = $this->shoppingCart()->get();

        if (empty($cart['count']) || (int) $cart['count'] <= 0) {
            return redirect()->route('kosarica');
        }

        $data = $this->checkSession($cart);

        if (isset($data['_redirect_step'])) {
            return redirect()->route('naplata', ['step' => $data['_redirect_step']]);
        }

        if (empty($data)) {
            return redirect()->route('naplata', ['step' => 'podaci']);
        }

        $data = $this->collectData($data, config('settings.order.status.unfinished'));

        $order = new Order();

        if (CheckoutSession::hasOrder()) {
            $data['id'] = CheckoutSession::getOrder()['id'];

            $order->updateData($data);
            $order->setData($data['id']);

        } else {
            $order->createFrom($data);
        }

        if ($order->isCreated()) {
            CheckoutSession::setOrder($order->getData());
        }

        if ( ! isset($data['id'])) {
            $data['id'] = CheckoutSession::getOrder()['id'];
        }

        $uvjeti = DB::table('pages')
                    ->select('description')
                    ->whereIn('id', [6])
                    ->get();

        $data['payment_form'] = $order->resolvePaymentForm();

        return view('front.checkout.view', compact('data', 'uvjeti'));
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function order(Request $request)
    {
        $order = new Order();

        $this->logWspayReturn($request, 'received');

        if ($request->has('provjera')) {
            $order->setData($request->input('provjera'));
        }

        if ($request->has('order_number')) {
            $order->setData($request->input('order_number'));
        }

        if ($request->has('ShoppingCartID')) {
            $id = $this->orderIdFromShoppingCartId($request->input('ShoppingCartID'));

            Log::channel('wspay')->info('WSPay ShoppingCartID resolved', [
                'shopping_cart_id' => $request->input('ShoppingCartID'),
                'order_id' => $id,
            ]);

            $order->setData($id);
        }

        // paypal standard
        if ($request->has('PayerID')) {
            $order->setData(isset(CheckoutSession::getOrder()['id']) ? CheckoutSession::getOrder()['id'] : 0);
        }

        $finished = $order->finish($request);

        $this->logWspayReturn($request, 'finish_result', [
            'finished' => (bool) $finished,
        ]);

        if ($finished) {
            if ($request->has('return_json') && intval($request->input('return_json'))) {
                return response()->json(['success' => 1, 'href' => route('checkout.success')]);
            }

            return redirect()->route('checkout.success');
        }

        return redirect()->route('checkout.error');
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function wspayCallback(Request $request)
    {
        $result = (new Wspay(new BackOrder()))->handleCallback($request);
        $status = $result['http_status'] ?? 200;

        unset($result['http_status']);

        return response()->json($result, $status);
    }


    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function success(Request $request)
    {
        $data['order'] = CheckoutSession::getOrder();

        if ( ! $data['order']) {
            return redirect()->route('index');
        }

        $order = \App\Models\Back\Orders\Order::where('id', $data['order']['id'])->first();

        if ($order) {
            dispatch(function () use ($order) {
                Mail::to(config('mail.admin'))->send(new OrderReceived($order));
                Mail::to($order->payment_email)->send(new OrderSent($order));
            })->afterResponse();

            foreach ($order->products as $product) {
                $real = $product->real;

                if ($real->decrease && $real->quantity) {
                    $real->decrement('quantity', $product->quantity);
                }
            }

            $this->forgetCheckoutCache();

            $cart = $this->shoppingCart();
            $cart->flush()->resolveDB();

            $data['google_tag_manager'] = TagManager::getGoogleSuccessDataLayer($order);

            return view('front.checkout.success', compact('data'));
        }

        return redirect()->route('front.checkout.checkout', ['step' => '']);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function successKeks(Request $request)
    {
        if ($this->validateKeksResponse($request)) {
            $id    = substr($request->input('bill_id'), 16);
            $order = Order::query()->where('id', $id)->first();

            $order->setData($id)->finish($request);

            $order->update([
                'order_status_id' => config('settings.order.new_status')
            ]);

            $this->forgetCheckoutCache();

            return response()->json(['status' => 0, 'message' => 'Accepted']);
        }

        return response()->json(['status' => 1, 'message' => 'Failed']);
    }


    public function successPaypal(Request $request)
    {
        Log::info('public function successPaypal(Request $request)');
        Log::info($request->toArray());

        $order = new Order();

        // paypal standard
        if ($request->has('PayerID') && $request->has('custom')) {
            $order->setData($request->input('custom'));
        }

        if ($order->finish($request)) {
            if ($request->has('return_json') && intval($request->input('return_json'))) {
                return response()->json(['success' => 1, 'href' => route('checkout.success')]);
            }

            return redirect()->route('checkout.success');
        }

        return redirect()->route('checkout.error');
    }


    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function error()
    {
        return view('front.checkout.error');
    }


    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * @return array
     */
    private function checkSession(array $cart): array
    {
        if (! CheckoutSession::hasAddress()) {
            return [];
        }

        $address = (array) CheckoutSession::getAddress();
        $state = trim((string) ($address['state'] ?? '')) ?: 'Croatia';
        $geo = (new GeoZone())->findState($state);

        if (! isset($geo->id)) {
            return $this->invalidateShippingSelection();
        }

        $shippingCode = trim((string) CheckoutSession::getShipping());
        $availableShipping = (new ShippingMethod())
            ->findGeo((int) $geo->id)
            ->checkCart($cart)
            ->resolve();

        if ($shippingCode === ''
            || ! $availableShipping->contains(function ($method) use ($shippingCode) {
                return (string) $method->code === $shippingCode;
            })) {
            return $this->invalidateShippingSelection();
        }

        if ($shippingCode === 'boxnow' && ! $this->hasValidBoxNowLocker()) {
            CheckoutSession::forgetCommentp();

            return ['_redirect_step' => 'dostava'];
        }

        $paymentCode = trim((string) CheckoutSession::getPayment());
        $availablePayments = (new PaymentMethod())
            ->findGeo((int) $geo->id)
            ->checkShipping($shippingCode)
            ->checkCart($cart)
            ->resolve();

        if ($paymentCode === ''
            || ! $availablePayments->contains(function ($method) use ($paymentCode) {
                return (string) $method->code === $paymentCode;
            })) {
            CheckoutSession::forgetPayment();

            return ['_redirect_step' => 'placanje'];
        }

        return [
            'address' => $address,
            'shipping' => $shippingCode,
            'payment' => $paymentCode,
            'comment' => CheckoutSession::getComment(),
            'commentp' => CheckoutSession::getCommentp(),
        ];
    }


    private function invalidateShippingSelection(): array
    {
        CheckoutSession::forgetShipping();
        CheckoutSession::forgetCommentp();
        CheckoutSession::forgetPayment();

        return ['_redirect_step' => 'dostava'];
    }


    private function hasValidBoxNowLocker(): bool
    {
        $pickup = trim((string) CheckoutSession::getCommentp());
        $separator = strrpos($pickup, '_');
        $lockerId = $separator === false ? '' : trim(substr($pickup, $separator + 1));

        return $separator !== false
            && $lockerId !== ''
            && strlen($lockerId) <= 191
            && preg_match('/^[A-Za-z0-9-]+$/', $lockerId) === 1;
    }


    /**
     * @param array $data
     * @param int   $order_status_id
     *
     * @return array
     */
    private function collectData(array $data, int $order_status_id): array
    {
        $shipping = Settings::getList('shipping')->where('code', $data['shipping'])->first();
        $payment  = Settings::getList('payment')->where('code', $data['payment'])->first();

        $response                    = [];
        $response['address']         = isset($data['address']) ? $data['address'] : [];
        $response['shipping']        = $shipping;
        $response['payment']         = $payment;
        $response['comment']         = isset($data['comment']) ? $data['comment'] : '';
        $response['commentp']         = isset($data['commentp']) ? $data['commentp'] : '';
        $response['cart']            = $this->shoppingCart()->get();
        $response['order_status_id'] = $order_status_id;

        return $response;
    }


    /**
     * @param Request $request
     *
     * @return bool
     */
    private function validateKeksResponse(Request $request): bool
    {
        if ($request->has('status') && ! $request->input('status')) {
            $token = $request->header('Php-Auth-Pw');

            if ($token) {
                $keks_token = Settings::get('payment', 'list.keks')->first();

                if (isset($keks_token->data->token)) {
                    $request->validate(['bill_id' => 'required']);

                    return hash_equals($keks_token->data->token, $token);
                }
            }
        }

        return false;
    }


    /**
     * @param Request    $request
     * @param string     $stage
     * @param array|null $extra
     */
    private function logWspayReturn(Request $request, string $stage, ?array $extra = null): void
    {
        if ( ! $this->isWspayReturn($request)) {
            return;
        }

        Log::channel('wspay')->info('WSPay return ' . $stage, array_merge([
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'shopping_cart_id' => $request->input('ShoppingCartID'),
            'order_id' => $request->has('ShoppingCartID') ? $this->orderIdFromShoppingCartId($request->input('ShoppingCartID')) : null,
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
            'signature' => $this->wspaySignatureMeta($request->input('Signature')),
            'received_keys' => array_keys($request->all()),
        ], $extra ?: []));
    }


    /**
     * @param Request $request
     *
     * @return bool
     */
    private function isWspayReturn(Request $request): bool
    {
        $keys = [
            'ShoppingCartID',
            'WsPayOrderId',
            'Success',
            'ApprovalCode',
            'ErrorMessage',
            'ErrorCodes',
            'ResponseCode',
            'STAN',
        ];

        foreach ($keys as $key) {
            if ($request->has($key)) {
                return true;
            }
        }

        return false;
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
     * @param string|null $signature
     *
     * @return array
     */
    private function wspaySignatureMeta(?string $signature): array
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
     * @return AgCart
     */
    private function shoppingCart(): AgCart
    {
        if (session()->has(config('session.cart'))) {
            return new AgCart(session(config('session.cart')));
        }

        return new AgCart(config('session.cart'));
    }


    private function forgetCheckoutCache()
    {
        CheckoutSession::forgetOrder();
        CheckoutSession::forgetStep();
        CheckoutSession::forgetPayment();
        CheckoutSession::forgetShipping();
        CheckoutSession::forgetComment();
        CheckoutSession::forgetCommentp();
    }

}
