<?php

namespace App\Http\Controllers\Front;

use App\Exceptions\InsufficientStockException;
use App\Helpers\Session\CheckoutSession;
use App\Http\Controllers\Controller;
use App\Models\Back\Orders\Order as BackOrder;
use App\Models\Back\Settings\Settings;
use App\Models\Front\AgCart;
use App\Models\Front\Checkout\GeoZone;
use App\Models\Front\Checkout\Order;
use App\Models\Front\Checkout\PaymentMethod;
use App\Models\Front\Checkout\ShippingMethod;
use App\Models\Front\Checkout\Payment\Wspay;
use App\Models\Front\Checkout\Payment\PayPalStandard;
use App\Models\Front\Checkout\Shipping\Gls;
use App\Models\TagManager;
use App\Services\Inventory\OrderInventoryService;
use App\Services\Orders\OrderConfirmationService;
use App\Services\Payments\PaymentAttemptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SoapClient;
use \stdClass;

class CheckoutController extends Controller
{
    private const LOCAL_PAYMENT_CODES = ['cod', 'bank', 'pickup'];
    private const CONFIRMABLE_ORDER_STATUSES = [1, 2, 3, 4, 9, 10, 11];

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
    public function view(Request $request, OrderInventoryService $inventory)
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
        $alreadyConfirmed = false;

        try {
            DB::transaction(function () use ($order, $data, $inventory, &$alreadyConfirmed) {
                $sessionOrderId = (int) data_get(CheckoutSession::getOrder(), 'id', 0);
                $existingOrder = $sessionOrderId
                    ? BackOrder::query()->where('id', $sessionOrderId)->lockForUpdate()->first()
                    : null;
                $alreadyConfirmed = $existingOrder
                    && in_array(
                        (int) $existingOrder->order_status_id,
                        self::CONFIRMABLE_ORDER_STATUSES,
                        true
                    )
                    && $existingOrder->inventory_committed_at !== null
                    && $existingOrder->inventory_released_at === null
                    && ! $existingOrder->inventory_allocation_error
                    && ! $existingOrder->payment_review_error;

                if ($alreadyConfirmed) {
                    $order->setData((string) $existingOrder->id);

                    return;
                }

                $startedAttemptMatches = $existingOrder
                    && $existingOrder->payment_attempt_started_at !== null
                    && $existingOrder->inventory_committed_at === null
                    && $existingOrder->inventory_released_at === null
                    && app(PaymentAttemptService::class)->matchesCheckoutData($existingOrder, $data);

                if ($startedAttemptMatches) {
                    $order->setData((string) $existingOrder->id);

                    return;
                }

                $canReuseOrder = $existingOrder
                    && (int) $existingOrder->order_status_id === (int) config('settings.order.status.unfinished')
                    && $existingOrder->inventory_committed_at === null
                    && $existingOrder->payment_attempt_started_at === null;

                if ($canReuseOrder) {
                    $data['id'] = $sessionOrderId;

                    $order->updateData($data);
                    $order->setData($data['id']);
                } else {
                    CheckoutSession::forgetOrder();
                    $order->createFrom($data);
                }

                if (! $order->isCreated()) {
                    throw new \RuntimeException('Narudžbu nije moguće pripremiti za plaćanje.');
                }

                $reserved = $inventory->reserve(
                    BackOrder::query()->findOrFail($order->getData()->id),
                    now()->addMinutes((int) config('settings.order.inventory_reservation_minutes', 30)),
                    'checkout_preview'
                );

                $order->setData((string) $reserved->id);
            });
        } catch (InsufficientStockException $exception) {
            return redirect()->route('kosarica')->with('error', $exception->getMessage());
        }

        if ($alreadyConfirmed) {
            return redirect()->route('checkout.success');
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
        $isLocalCheckout = false;
        $expectedPaymentCode = null;

        $this->logWspayReturn($request, 'received');

        $identifiers = collect(['provjera', 'order_number', 'ShoppingCartID'])
            ->filter(function ($key) use ($request) {
                return $request->has($key);
            });

        if ($identifiers->count() > 1) {
            abort(422, 'Povrat sadrži više različitih oznaka narudžbe.');
        }

        if ($request->has('provjera')) {
            if (! $request->isMethod('post')) {
                abort(405, 'Lokalnu narudžbu moguće je potvrditi samo zaštićenim obrascem.');
            }

            $requestedOrderId = (int) $request->input('provjera');
            $sessionOrderId = (int) data_get(CheckoutSession::getOrder(), 'id', 0);
            $localOrder = $sessionOrderId
                ? BackOrder::query()->where('id', $sessionOrderId)->first()
                : null;

            if (! $requestedOrderId
                || $requestedOrderId !== $sessionOrderId
                || ! $localOrder
                || (int) $localOrder->order_status_id !== (int) config('settings.order.status.unfinished')
                || ! in_array(strtolower((string) $localOrder->payment_code), self::LOCAL_PAYMENT_CODES, true)) {
                abort(403, 'Ovu narudžbu nije moguće potvrditi iz trenutne sesije.');
            }

            $isLocalCheckout = true;
            $expectedPaymentCode = strtolower((string) $localOrder->payment_code);
            $order->setData((string) $sessionOrderId);
        } else {
            if ($request->has('order_number')) {
                $expectedPaymentCode = 'corvus';
                $order->setData($request->input('order_number'));
            }

            if ($request->has('ShoppingCartID')) {
                $expectedPaymentCode = 'wspay';
                $wspayOrder = Wspay::findOrderByReference($request->input('ShoppingCartID'));
                $id = $wspayOrder ? (string) $wspayOrder->id : '';

                if ($id === '') {
                    abort(422, 'WSPay oznaka narudžbe nije valjana.');
                }

                Log::channel('wspay')->info('WSPay ShoppingCartID resolved', [
                    'shopping_cart_id' => $request->input('ShoppingCartID'),
                    'order_id' => $id,
                ]);

                $order->setData($id);
            }

        }

        if ($order->isCreated()
            && in_array(strtolower((string) $order->getData()->payment_code), self::LOCAL_PAYMENT_CODES, true)
            && ! $isLocalCheckout) {
            abort(403, 'Lokalnu narudžbu nije moguće potvrditi preko vanjskog povrata.');
        }

        $finished = false;

        if ($expectedPaymentCode !== null) {
            try {
                $finished = $order->finish($request, $expectedPaymentCode);
            } catch (InsufficientStockException $exception) {
                return redirect()->route('kosarica')->with('error', $exception->getMessage());
            }
        }

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

        if (! empty($result['payment_completed']) && isset($result['order_id'])) {
            $order = BackOrder::query()->find((int) $result['order_id']);

            if ($order
                && $order->inventory_committed_at !== null
                && $order->inventory_released_at === null
                && ! $order->inventory_allocation_error
                && ! $order->payment_review_error) {
                app(OrderConfirmationService::class)->dispatchAfterResponse($order);
            }
        }

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

        if ($order
            && in_array(
                (int) $order->order_status_id,
                self::CONFIRMABLE_ORDER_STATUSES,
                true
            )
            && $order->inventory_committed_at !== null
            && $order->inventory_released_at === null
            && ! $order->inventory_allocation_error
            && ! $order->payment_review_error) {
            app(OrderConfirmationService::class)->dispatchAfterResponse($order);

            $this->forgetCheckoutCache();

            $cart = $this->shoppingCart();
            $cart->flush()->resolveDB();

            $data['google_tag_manager'] = TagManager::getGoogleSuccessDataLayer($order);

            return view('front.checkout.success', compact('data'));
        }

        if ($order && ($order->inventory_allocation_error || $order->payment_review_error)) {
            return redirect()->route('checkout.error')->with(
                'error',
                'Plaćanje je zaprimljeno, ali dostupnost narudžbe mora provjeriti djelatnik. Nemojte ponavljati plaćanje.'
            );
        }

        if ($order && in_array((int) $order->order_status_id, [5, 6, 7, 12, 14], true)) {
            return redirect()->route('checkout.error');
        }

        return redirect()->route('pregled')->with('error', 'Narudžba još nije potvrđena.');
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

            $order->setData($id)->finish($request, 'keks');

            $order->update([
                'order_status_id' => config('settings.order.new_status')
            ]);

            $this->forgetCheckoutCache();

            return response()->json(['status' => 0, 'message' => 'Accepted']);
        }

        return response()->json(['status' => 1, 'message' => 'Failed']);
    }


    public function paypalNotification(Request $request)
    {
        $order = PayPalStandard::findNotificationOrder((string) $request->input('custom', ''));

        if (! $order) {
            Log::warning('PayPal IPN rejected before provider verification.', [
                'custom_present' => filled($request->input('custom')),
            ]);

            return response('INVALID', 400);
        }

        $result = (new PayPalStandard($order))->handleNotification($order, $request);
        $fresh = $order->fresh();

        if (! empty($result['should_notify'])
            && $fresh->inventory_committed_at !== null
            && $fresh->inventory_released_at === null
            && ! $fresh->inventory_allocation_error
            && ! $fresh->payment_review_error) {
            app(OrderConfirmationService::class)->dispatchAfterResponse($fresh);
        }

        return response($result['message'], $result['http_status']);
    }


    public function paypalReturn(Request $request)
    {
        $attempt = trim((string) $request->query('attempt', ''));

        if ($attempt !== '') {
            $order = PayPalStandard::findNotificationOrder($attempt);
        } else {
            // Compatibility for PayPal forms opened before the return URL
            // started carrying the payment-attempt reference.
            $sessionOrderId = (int) data_get(CheckoutSession::getOrder(), 'id', 0);
            $order = $sessionOrderId ? BackOrder::query()->find($sessionOrderId) : null;
        }

        if (! $order || strtolower((string) $order->payment_code) !== 'paypal') {
            return redirect()->route('index');
        }

        if ($order->inventory_allocation_error || $order->payment_review_error) {
            return redirect()->route('checkout.error')->with(
                'error',
                'Plaćanje je zaprimljeno, ali narudžbu mora provjeriti djelatnik. Nemojte ponavljati plaćanje.'
            );
        }

        if (in_array(
                (int) $order->order_status_id,
                self::CONFIRMABLE_ORDER_STATUSES,
                true
            )
            && $order->inventory_committed_at !== null
            && $order->inventory_released_at === null) {
            return redirect()->route('checkout.success');
        }

        if (in_array((int) $order->order_status_id, [
            (int) config('settings.order.status.canceled'),
            (int) config('settings.order.status.declined'),
            (int) config('settings.order.status.returned'),
            (int) config('settings.order.status.refund'),
            (int) config('settings.order.status.blacklist'),
        ], true)) {
            return redirect()->route('checkout.error');
        }

        return view('front.checkout.payment_pending', compact('order', 'attempt'));
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
    private function orderIdFromShoppingCartId($shopping_cart_id): string
    {
        if (! is_string($shopping_cart_id) && ! is_int($shopping_cart_id)) {
            return '';
        }

        return preg_match('/^(\d+)-\d{4}$/D', (string) $shopping_cart_id, $matches)
            ? $matches[1]
            : '';
    }


    /**
     * @param string|null $signature
     *
     * @return array
     */
    private function wspaySignatureMeta($signature): array
    {
        if (! is_string($signature) || $signature === '') {
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
