<?php

namespace App\Models\Front\Checkout\Payment;

use App\Helpers\Country;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderHistory;
use App\Models\Back\Orders\Transaction;
use App\Models\Back\Settings\Settings;
use App\Services\Inventory\OrderInventoryService;
use App\Services\Payments\DecimalAmount;
use App\Services\Payments\PaymentAttemptService;
use App\Services\Payments\ProviderReferenceService;
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
    private const INVENTORY_HOLDING_STATUSES = [1, 2, 3, 4, 9, 10, 11];
    private const LEGACY_CALLBACK_WINDOW_DAYS = 4;
    private const RANDOM_REFERENCE_PATTERN = '/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/D';

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

        if (! $this->hasUsableCredentials($payment_method)) {
            throw new \RuntimeException('WSPay postavke plaćanja nisu potpune.', 409);
        }

        $currentTestMode = (bool) $payment_method->data->test;
        $this->order = app(PaymentAttemptService::class)->start(
            $this->order,
            'wspay',
            null,
            'EUR',
            $currentTestMode ? 'test' : 'live',
            (string) $payment_method->data->shop_id,
            (string) $payment_method->data->secret_key
        );

        $shoppingcartid = (string) $this->order->payment_attempt_reference;
        $testMode = $this->order->payment_attempt_environment === 'test';
        $shopId = (string) $this->order->payment_attempt_merchant;
        $secretKey = app(PaymentAttemptService::class)->verificationSecret($this->order);

        if (! $secretKey) {
            throw new \RuntimeException(
                'WSPay ključ pokušaja plaćanja nije dostupan. Potrebna je ručna provjera.',
                409
            );
        }

        $action = $this->url[$testMode ? 'test' : 'live'];
        $amountMinor = (int) $this->order->payment_expected_amount_minor;
        $total = str_replace('.', ',', DecimalAmount::format($amountMinor));
        // WSPay signs the exact TotalAmount text after removing separators.
        // Keep the leading zero for sub-euro totals (for example 0,50 -> 050).
        $_total = str_replace([',', '.'], '', $total);

        $hash = hash('sha512', $shopId .
            $secretKey .
            $shoppingcartid .
            $secretKey .
            $_total .
            $secretKey
        );

        $data['action'] = $action;
        $data['shop_id'] = $shopId;
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
            'mode' => $testMode ? 'test' : 'live',
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
        $fields_valid = $this->validBrowserFields($request);
        $success = (string) $request->input('Success') === '1';
        $requested_status = $success ? config('settings.order.status.paid') : config('settings.order.status.declined');
        $signature_valid = $fields_valid ? $this->validResponseSignature($request, $order) : false;
        $payment_code_valid = $order->payment_code === 'wspay';
        $amount_valid = $fields_valid
            && (! $success || $this->validResponseAmount($order, $request->input('Amount')));
        $attempt_valid = $fields_valid
            && $this->validAttemptReference($order, $request->input('ShoppingCartID'));
        $identifiers_valid = $fields_valid && (! $success || $this->validPaymentIdentifiers($request));
        $snapshot_valid = $order->payment_attempt_started_at === null
            || app(PaymentAttemptService::class)->matchesSnapshot($order);

        Log::channel('wspay')->info('WSPay finish started', [
            'order_id' => $order->id,
            'current_status_id' => $order->order_status_id,
            'target_status_id' => $requested_status,
            'success' => $success,
            'signature_valid' => $signature_valid,
            'payment_code_valid' => $payment_code_valid,
            'amount_valid' => $amount_valid,
            'attempt_valid' => $attempt_valid,
            'snapshot_valid' => $snapshot_valid,
            'identifiers_valid' => $identifiers_valid,
            'fields_valid' => $fields_valid,
            'response' => $this->responseContext($request),
        ]);

        if (! $fields_valid
            || $signature_valid !== true
            || ! $payment_code_valid
            || ! $amount_valid
            || ! $attempt_valid
            || ! $identifiers_valid) {
            Log::channel('wspay')->warning('WSPay finish rejected: invalid response', [
                'order_id' => $order->id,
                'signature_valid' => $signature_valid,
                'payment_code_valid' => $payment_code_valid,
                'amount_valid' => $amount_valid,
                'attempt_valid' => $attempt_valid,
                'snapshot_valid' => $snapshot_valid,
                'identifiers_valid' => $identifiers_valid,
                'response' => $this->responseContext($request),
            ]);

            return false;
        }

        try {
            return DB::transaction(function () use ($order, $request, $success) {
                $locked_order = Order::query()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $this->validBrowserFields($request)
                    || $this->validResponseSignature($request, $locked_order) !== true
                    || $locked_order->payment_code !== 'wspay'
                    || ! $this->validAttemptReference($locked_order, $request->input('ShoppingCartID'))
                    || ($success && ! $this->validPaymentIdentifiers($request))
                    || ($success && ! $this->validResponseAmount($locked_order, $request->input('Amount')))) {
                    return false;
                }

                $previous_status = (int) $locked_order->order_status_id;
                $target_status = $this->browserStatus($success, $previous_status);
                $providerOrderId = trim((string) $request->input('WsPayOrderId', ''));
                $snapshotConflict = $success
                    && $locked_order->payment_attempt_started_at !== null
                    && ! app(PaymentAttemptService::class)->matchesSnapshot($locked_order);
                $terminalConflict = $success && $this->isTerminalStatus($previous_status);
                $differentPayment = $success
                    && $this->hasDifferentPriorPayment($locked_order, $providerOrderId);
                $manualConflict = $snapshotConflict || $terminalConflict || $differentPayment;

                if ($manualConflict) {
                    // Preserve the operational status. In particular, keeping an
                    // unfinished reservation unfinished lets its TTL release it.
                    $target_status = $previous_status;
                }

                $updates = [];

                if ($target_status !== $previous_status) {
                    $updates['order_status_id'] = $target_status;
                }

                if ($manualConflict) {
                    $updates['payment_review_error'] =
                        'WSPay uspjeh ne odgovara zamrznutoj ili postojećoj transakciji narudžbe. Potrebna je ručna provjera.';
                }

                if ($updates) {
                    $locked_order->forceFill($updates)->saveOrFail();
                }

                $this->insertTransaction($locked_order, $request, $success);

                if ($manualConflict) {
                    OrderHistory::insert([
                        'order_id' => $locked_order->id,
                        'user_id' => 0,
                        'status' => $target_status,
                        'comment' => 'WSPay uspjeh evidentiran bez automatske promjene zalihe; potrebna je ručna provjera.',
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }

                Log::channel('wspay')->info('WSPay order status update finished', [
                    'order_id' => $locked_order->id,
                    'previous_status_id' => $previous_status,
                    'status_id' => $target_status,
                    'manual_review' => $manualConflict,
                ]);

                return $success;
            });
        } catch (\RuntimeException $exception) {
            if ($exception->getCode() !== 409) {
                throw $exception;
            }

            Log::channel('wspay')->warning('WSPay browser return rejected by idempotency guard.', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }


    private function transactionIdempotencyKey(
        Order $order,
        Request $request,
        ?string $provider_order_id
    ): ?string
    {
        $event = $this->providerEvent($request, false);

        if ($provider_order_id) {
            $parts = ['wspay', 'provider', $provider_order_id];

            // Retain the historical payment key so callback and browser success
            // deduplicate against the migration backfill. Other events are
            // append-only audit rows and must never overwrite that payment.
            if ($event !== 'payment') {
                $parts[] = 'event';
                $parts[] = $event;
                $unique = trim((string) $request->input('UniqueTransactionNumber', ''));

                if ($unique !== '') {
                    $parts[] = 'unique';
                    $parts[] = $unique;
                }
            }

            return hash('sha256', implode('|', $parts));
        }

        $approval_code = trim((string) $request->input('ApprovalCode', ''));

        if ($approval_code !== '') {
            return hash('sha256', implode('|', [
                'wspay',
                'order',
                (int) $order->id,
                'approval',
                $approval_code,
                'event',
                $event,
            ]));
        }

        $stan = trim((string) $request->input('STAN', ''));

        if ($stan !== '') {
            return hash('sha256', implode('|', [
                'wspay',
                'order',
                (int) $order->id,
                'stan',
                $stan,
                'event',
                $event,
            ]));
        }

        // Without a stable identifier, retaining separate audit events is safer
        // than collapsing unrelated retries solely because the order is the same.
        return null;
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

        $signatureOrder = self::findOrderByReference($request->input('ShoppingCartID'));
        $order_id = $signatureOrder ? (int) $signatureOrder->id : 0;

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

        if (! $this->validCallbackFields($request)) {
            Log::channel('wspay')->warning('WSPay callback rejected: malformed fields', [
                'order_id' => $order_id,
                'callback' => $this->callbackContext($request),
            ]);

            return [
                'success' => false,
                'message' => 'Malformed callback fields',
                'order_id' => $order_id,
                'http_status' => 422,
            ];
        }

        if ($this->validCallbackSignature($request, $signatureOrder) !== true) {
            Log::channel('wspay')->warning('WSPay callback rejected: invalid signature', [
                'order_id' => $order_id,
                'callback' => $this->callbackContext($request),
            ]);

            return [
                'success' => false,
                'message' => 'Invalid signature',
                'http_status' => 403,
            ];
        }

        try {
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

            if (! $this->validCallbackFields($request)
                || $this->validCallbackSignature($request, $order) !== true) {
                return [
                    'success' => false,
                    'message' => 'Invalid signature',
                    'order_id' => $order->id,
                    'http_status' => 403,
                ];
            }

            if (! $this->validAttemptReference($order, $request->input('ShoppingCartID'))) {
                return [
                    'success' => false,
                    'message' => 'Payment attempt does not match order',
                    'order_id' => $order->id,
                    'http_status' => 409,
                ];
            }

            if ($this->looksLikeSuccessfulCallback($request) && ! $this->successfulCallback($request)) {
                return [
                    'success' => false,
                    'message' => 'Incomplete successful payment data',
                    'order_id' => $order->id,
                    'http_status' => 422,
                ];
            }

            if ($this->successfulCallback($request)
                && ! $this->validResponseAmount($order, $request->input('Amount'))) {
                return [
                    'success' => false,
                    'message' => 'Amount does not match order total',
                    'order_id' => $order->id,
                    'http_status' => 422,
                ];
            }

            if ($this->successfulCallback($request)
                && ! $this->validCallbackCurrency($request->input('CurrencyCode'))) {
                return [
                    'success' => false,
                    'message' => 'Unexpected currency',
                    'order_id' => $order->id,
                    'http_status' => 422,
                ];
            }

            $previous_status = (int) $order->order_status_id;
            $terminalProviderEvent = $this->isTerminalProviderEvent($request);
            $completedPayment = $this->successfulCallback($request);
            $providerOrderId = trim((string) $request->input('WsPayOrderId', ''));
            $legacyAuditOnly = $order->payment_attempt_started_at === null
                && ! $this->legacyCallbackMayMutate($order);
            $snapshotConflict = ($completedPayment || $terminalProviderEvent)
                && $order->payment_attempt_started_at !== null
                && ! app(PaymentAttemptService::class)->matchesSnapshot($order);
            $terminalConflict = $completedPayment
                && $this->isTerminalStatus($previous_status);
            $differentPayment = $completedPayment
                && $this->hasDifferentPriorPayment($order, $providerOrderId);
            $target_status = $legacyAuditOnly
                ? null
                : $this->callbackStatus($request, $previous_status);
            $terminalParentValid = ! $terminalProviderEvent
                || $this->hasPriorPayment($order, $request);
            $manualConflict = $snapshotConflict
                || $terminalConflict
                || $differentPayment
                || $terminalProviderEvent;

            if ($manualConflict) {
                $target_status = null;
            }

            $transactionOperation = $this->insertTransaction($order, $request, $completedPayment, true);

            if ($terminalProviderEvent && ! $terminalParentValid) {
                $order->forceFill([
                    'payment_review_error' =>
                        'WSPay storno/povrat nema odgovarajuću izvornu uplatu. Potrebna je ručna provjera.',
                ])->saveOrFail();

                if ($transactionOperation === 'inserted') {
                    OrderHistory::insert([
                        'order_id' => $order->id,
                        'user_id' => 0,
                        'status' => $previous_status,
                        'comment' => 'WSPay storno/povrat evidentiran bez promjene statusa ili zalihe; izvorna uplata nije pronađena.',
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }

                Log::channel('wspay')->critical('Unmatched WSPay terminal event was recorded without mutation.', [
                    'order_id' => $order->id,
                    'wspay_order_id' => $request->input('WsPayOrderId'),
                    'event' => $this->providerEvent($request, false),
                ]);

                return [
                    'success' => false,
                    'message' => 'Terminal payment event requires manual verification',
                    'order_id' => $order->id,
                    'previous_status_id' => $previous_status,
                    'status_id' => $previous_status,
                    'changed' => false,
                    'inventory_allocated' => false,
                    'inventory_error' => 'Unmatched terminal WSPay event',
                    'payment_completed' => false,
                    'http_status' => 200,
                ];
            }

            if ($target_status && $target_status !== $previous_status) {
                $updates = [
                    'order_status_id' => $target_status,
                    'payment_card' => $request->input('CreditCardName') ?: $order->payment_card,
                    'payment_installment' => $this->installmentCount($request->input('PaymentPlan')),
                ];

                if ($manualConflict) {
                    $updates['payment_review_error'] =
                        'WSPay događaji plaćanja nisu u očekivanom redoslijedu. Potrebna je ručna provjera.';
                }

                $order->forceFill($updates)->saveOrFail();

                OrderHistory::insert([
                    'order_id' => $order->id,
                    'user_id' => 0,
                    'status' => $target_status,
                    'comment' => 'WSPay callback status: ' . $this->callbackStatusLabel($request),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            } elseif ($manualConflict) {
                $reviewMessage = $terminalProviderEvent
                    ? 'WSPay storno/povrat evidentiran je samo za ručnu provjeru; zaliha nije automatski vraćena.'
                    : 'WSPay događaji plaćanja nisu u očekivanom redoslijedu. Potrebna je ručna provjera.';
                $order->forceFill([
                    'payment_review_error' => $reviewMessage,
                ])->saveOrFail();

                if ($transactionOperation === 'inserted') {
                    OrderHistory::insert([
                        'order_id' => $order->id,
                        'user_id' => 0,
                        'status' => $previous_status,
                        'comment' => 'WSPay događaj evidentiran bez automatske promjene statusa ili zalihe.',
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
            }

            $effective_status = $target_status ?: $previous_status;
            $inventory_allocated = true;
            $order->refresh();
            $existingError = $order->inventory_allocation_error ?: $order->payment_review_error;
            $inventory_error = $manualConflict
                ? ($order->payment_review_error ?: 'WSPay event requires manual resolution.')
                : ($legacyAuditOnly
                    ? 'Legacy WSPay callback was recorded without changing order or inventory.'
                    : ($existingError ?: null));

            try {
                if ($manualConflict) {
                    Log::channel('wspay')->critical('Late WSPay success requires manual resolution', [
                        'order_id' => $order->id,
                        'previous_status_id' => $previous_status,
                        'wspay_order_id' => $request->input('WsPayOrderId'),
                    ]);

                    $inventory_allocated = false;
                } elseif ($existingError) {
                    $inventory_allocated = false;
                } elseif (! $legacyAuditOnly) {
                    $inventory = app(OrderInventoryService::class);
                    $inventory_order = $order->fresh();

                    $inventory_order = $inventory->applyStatusTransition(
                        $inventory_order,
                        $previous_status,
                        $effective_status,
                        'wspay_callback'
                    );

                    $inventory_allocated = $this->inventoryTransitionHandled(
                        $inventory,
                        $inventory_order,
                        $effective_status
                    );

                    if ( ! $inventory_allocated
                        && $target_status === config('settings.order.status.paid')) {
                        throw new \RuntimeException(
                            'Payment was confirmed without a committed inventory allocation.'
                        );
                    }
                }
            } catch (\Throwable $exception) {
                app(OrderInventoryService::class)->recordAllocationError($order, $exception);
                $inventory_allocated = false;
                $inventory_error = $exception->getMessage();

                if ($target_status === config('settings.order.status.paid')) {
                    $effective_status = config('settings.order.status.call_when_found');
                    $order->update(['order_status_id' => $effective_status]);

                    OrderHistory::insert([
                        'order_id' => $order->id,
                        'user_id' => 0,
                        'status' => $effective_status,
                        'comment' => 'WSPay payment confirmed, but inventory allocation failed: ' .
                            mb_substr($inventory_error, 0, 400),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);

                    Log::channel('wspay')->critical('WSPay payment requires manual inventory resolution', [
                        'order_id' => $order->id,
                        'status_id' => $effective_status,
                        'wspay_order_id' => $request->input('WsPayOrderId'),
                        'error' => $inventory_error,
                    ]);
                }
            }

            return [
                'success' => $inventory_error === null,
                'message' => $inventory_error === null
                    ? 'Accepted'
                    : 'Payment accepted; inventory requires manual resolution',
                'order_id' => $order->id,
                'previous_status_id' => $previous_status,
                'status_id' => $effective_status,
                'changed' => $effective_status !== $previous_status,
                'inventory_allocated' => $inventory_allocated,
                'inventory_error' => $inventory_error,
                'payment_completed' => $completedPayment
                    && $inventory_error === null
                    && $inventory_allocated,
                'http_status' => 200,
            ];
            });
        } catch (\RuntimeException $exception) {
            if ($exception->getCode() !== 409) {
                throw $exception;
            }

            $result = [
                'success' => false,
                'message' => $exception->getMessage(),
                'order_id' => $order_id,
                'http_status' => 409,
            ];
        }

        Log::channel('wspay')->info('WSPay callback processed', array_merge($result, [
            'callback' => $this->callbackContext($request),
        ]));

        return $result;
    }


    public static function findOrderByReference($reference): ?Order
    {
        if (! is_string($reference) && ! is_int($reference)) {
            return null;
        }

        $reference = trim((string) $reference);

        if (preg_match(self::RANDOM_REFERENCE_PATTERN, $reference)) {
            return Order::query()
                ->where('payment_code', 'wspay')
                ->where('payment_attempt_provider', 'wspay')
                ->where('payment_attempt_reference', $reference)
                ->first();
        }

        if (! preg_match('/^(\d+)-\d{4}$/D', $reference, $matches)) {
            return null;
        }

        // Compatibility for WSPay forms opened immediately before deployment.
        return Order::query()
            ->whereKey((int) $matches[1])
            ->where('payment_code', 'wspay')
            ->whereNull('payment_attempt_started_at')
            ->where('created_at', '>=', now()->subDays(self::LEGACY_CALLBACK_WINDOW_DAYS))
            ->first();
    }


    /**
     * @param Order   $order
     * @param Request $request
     * @param bool    $success
     * @param bool    $authoritative
     */
    private function insertTransaction(
        Order $order,
        Request $request,
        bool $success,
        bool $authoritative = false
    ): string
    {
        try {
            $provider_order_id = trim((string) $request->input('WsPayOrderId', '')) ?: null;

            if ($provider_order_id
                && ! app(ProviderReferenceService::class)->claim($order, 'wspay', $provider_order_id)) {
                throw new \RuntimeException(
                    'WSPay transaction identifier is already assigned to another order.',
                    409
                );
            }

            $idempotency_key = $this->transactionIdempotencyKey($order, $request, $provider_order_id);
            $now = Carbon::now();
            $attributes = [
                'order_id' => $order->id,
                'success' => $success ? 1 : 0,
                'amount' => $this->decimalAmount($request->input('Amount'), $order),
                'signature' => (string) $request->input('Signature', ''),
                'payment_type' => $this->nullableString(
                    $request->input('PaymentType') ?: $request->input('CreditCardName'),
                    16
                ),
                'payment_plan' => $this->nullableString($request->input('PaymentPlan'), 4),
                'payment_partner' => $success
                    ? $this->nullableString($request->input('Partner'), 191)
                    : null,
                'provider_event' => $this->providerEvent($request, $success),
                'datetime' => $this->transactionDateTime($request->input('DateTime') ?: $request->input('TransactionDateTime')),
                'approval_code' => $this->nullableString($request->input('ApprovalCode'), 191),
                'pg_order_id' => $provider_order_id,
                'idempotency_key' => $idempotency_key,
                'lang' => $this->nullableString($request->input('Lang'), 16) ?: '',
                'stan' => $this->nullableString($request->input('STAN'), 191),
                'error' => $this->nullableString($request->input('ErrorMessage'), 191),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $operation = DB::transaction(function () use ($order, $idempotency_key, $attributes, $authoritative) {
                // The order row is the lock shared by the browser return and callback.
                Order::query()
                     ->whereKey($order->id)
                     ->lockForUpdate()
                     ->first();

                $transaction = null;

                if ($idempotency_key) {
                    $transaction = Transaction::query()
                                              ->where('idempotency_key', $idempotency_key)
                                              ->orderBy('id')
                                              ->lockForUpdate()
                                              ->first();
                }

                if ($transaction) {
                    if ((int) $transaction->order_id !== (int) $order->id) {
                        throw new \RuntimeException(
                            'WSPay transaction identifier is already assigned to another order.',
                            409
                        );
                    }

                    if ($authoritative) {
                        $updates = $attributes;
                        unset($updates['order_id'], $updates['idempotency_key'], $updates['created_at']);

                        $transaction->forceFill($updates)->save();

                        return 'updated';
                    }

                    return 'duplicate_ignored';
                }

                Transaction::insert($attributes);

                return 'inserted';
            });

            Log::channel('wspay')->info('WSPay transaction row persisted', [
                'order_id' => $order->id,
                'pg_order_id' => $provider_order_id,
                'idempotency_key' => $idempotency_key,
                'success' => $success,
                'authoritative' => $authoritative,
                'operation' => $operation,
            ]);

            return $operation;
        } catch (\Throwable $exception) {
            Log::channel('wspay')->error('WSPay transaction row persistence failed', [
                'order_id' => $order->id,
                'success' => $success,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }


    private function validBrowserFields(Request $request): bool
    {
        if (! $this->validReferenceFormat($request->input('ShoppingCartID'))
            || ! in_array((string) $request->input('Success', ''), ['0', '1'], true)
            || ! $this->validString($request->input('Signature'), 128, false)
            || preg_match('/^[a-f0-9]{128}$/Di', (string) $request->input('Signature')) !== 1
            || ! $this->validOptionalString($request->input('ApprovalCode'), 191)
            || ! $this->validOptionalString($request->input('WsPayOrderId'), 191)
            || ! $this->validOptionalString($request->input('Amount'), 32)
            || ! $this->validOptionalString($request->input('PaymentType'), 16)
            || ! $this->validOptionalString($request->input('CreditCardName'), 16)
            || ! $this->validOptionalString($request->input('Partner'), 191)
            || ! $this->validOptionalString($request->input('STAN'), 191)
            || ! $this->validOptionalString($request->input('Lang'), 16)
            || ! $this->validOptionalString($request->input('ErrorMessage'), 191)
            || ! $this->validOptionalString($request->input('DateTime'), 32)) {
            return false;
        }

        $plan = $request->input('PaymentPlan');

        return ($plan === null || $plan === '' || (is_string($plan) && preg_match('/^\d{4}$/D', $plan)))
            && ($request->input('Amount') === null
                || $request->input('Amount') === ''
                || $this->amountInMinorUnits($request->input('Amount')) !== null);
    }


    private function validCallbackFields(Request $request): bool
    {
        foreach (['ActionSuccess', 'Authorized', 'Completed', 'Voided', 'Refunded', 'Reversed'] as $flag) {
            if (! $this->validFlagValue($request->input($flag))) {
                return false;
            }
        }

        $terminalCount = collect(['Voided', 'Refunded', 'Reversed'])
            ->filter(function ($flag) use ($request) {
                return $this->flag($request->input($flag));
            })
            ->count();
        $plan = $request->input('PaymentPlan');

        return $terminalCount <= 1
            && $this->validReferenceFormat($request->input('ShoppingCartID'))
            && $this->validString($request->input('ShopID'), 191, false)
            && $this->validString($request->input('Signature'), 128, false)
            && preg_match('/^[a-f0-9]{128}$/Di', (string) $request->input('Signature')) === 1
            && $this->validOptionalString($request->input('ApprovalCode'), 191)
            && $this->validOptionalString($request->input('WsPayOrderId'), 191)
            && $this->validOptionalString($request->input('Amount'), 32)
            && $this->validOptionalString($request->input('CurrencyCode'), 3)
            && $this->validOptionalString($request->input('UniqueTransactionNumber'), 191)
            && $this->validOptionalString($request->input('STAN'), 191)
            && $this->validOptionalString($request->input('Partner'), 191)
            && $this->validOptionalString($request->input('CreditCardName'), 16)
            && $this->validOptionalString($request->input('PaymentType'), 16)
            && $this->validOptionalString($request->input('TransactionDateTime'), 32)
            && $this->validOptionalString($request->input('Lang'), 16)
            && $this->validOptionalString($request->input('ErrorMessage'), 191)
            && ($plan === null || $plan === ''
                || (is_string($plan) && preg_match('/^\d{4}$/D', $plan)))
            && ($request->input('Amount') === null
                || $request->input('Amount') === ''
                || $this->amountInMinorUnits($request->input('Amount')) !== null);
    }


    private function validString($value, int $maximum, bool $emptyAllowed = true): bool
    {
        return (is_string($value) || is_int($value))
            && ($emptyAllowed || trim((string) $value) !== '')
            && mb_strlen((string) $value) <= $maximum;
    }


    private function validOptionalString($value, int $maximum): bool
    {
        return $value === null || $this->validString($value, $maximum);
    }


    private function validFlagValue($value): bool
    {
        if ($value === null || $value === '' || is_bool($value)) {
            return true;
        }

        if (is_int($value)) {
            return in_array($value, [0, 1], true);
        }

        return is_string($value)
            && in_array(strtolower($value), ['0', '1', 'true', 'false'], true);
    }


    private function nullableString($value, int $maximum): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $maximum);
    }


    /**
     * @param Request $request
     *
     * @return bool|null
     */
    private function validResponseSignature(Request $request, Order $order): ?bool
    {
        $credentials = $this->credentialsForOrder($order);

        if (! $credentials) {
            return null;
        }

        $signature = (string) $request->input('Signature', '');

        if ($signature === '') {
            return false;
        }

        $expected = hash('sha512', $credentials['shop_id'] .
            $credentials['secret_key'] .
            (string) $request->input('ShoppingCartID', '') .
            $credentials['secret_key'] .
            (string) $request->input('Success', '') .
            $credentials['secret_key'] .
            (string) $request->input('ApprovalCode', '') .
            $credentials['secret_key']
        );

        return hash_equals(strtolower($expected), strtolower($signature));
    }


    /**
     * @param Request $request
     *
     * @return bool|null
     */
    private function validCallbackSignature(Request $request, Order $order): ?bool
    {
        $credentials = $this->credentialsForOrder($order);

        if (! $credentials
            || ! hash_equals($credentials['shop_id'], trim((string) $request->input('ShopID', '')))) {
            return null;
        }

        $signature = (string) $request->input('Signature', '');

        if ($signature === '') {
            return false;
        }

        $expected = hash('sha512', $credentials['shop_id'] .
            $credentials['secret_key'] .
            (string) $request->input('ActionSuccess', '') .
            (string) $request->input('ApprovalCode', '') .
            $credentials['secret_key'] .
            $credentials['shop_id'] .
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
            'reversed' => $request->input('Reversed'),
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
        if ($this->isTerminalProviderEvent($request)) {
            // These flags are not part of WSPay's callback signature. Record
            // them for review, but never restock or change fulfilment status.
            return null;
        }

        if ($this->successfulCallback($request)) {
            $paid_status = (int) config('settings.order.status.paid');

            if ($this->isTerminalStatus($current_status)) {
                return null;
            }

            if (in_array($current_status, [
                (int) config('settings.order.status.unfinished'),
                $paid_status,
            ], true)) {
                return $paid_status;
            }

            // A replay may arrive after fulfilment has already advanced. Never
            // move shipped/ready/finished/manual-resolution orders back to paid.
            return null;
        }

        if ( ! $this->flag($request->input('ActionSuccess')) &&
            $current_status === config('settings.order.status.unfinished')) {
            return config('settings.order.status.declined');
        }

        return null;
    }


    private function browserStatus(bool $success, int $current_status): int
    {
        $unfinished_status = (int) config('settings.order.status.unfinished');

        if ( ! $success) {
            return $current_status === $unfinished_status
                ? (int) config('settings.order.status.declined')
                : $current_status;
        }

        if ($this->isTerminalStatus($current_status)) {
            return $current_status;
        }

        if (in_array($current_status, [
            $unfinished_status,
        ], true)) {
            return (int) config('settings.order.status.paid');
        }

        return $current_status;
    }


    private function successfulCallback(Request $request): bool
    {
        if ($this->flag($request->input('Refunded'))
            || $this->flag($request->input('Reversed'))
            || $this->flag($request->input('Voided'))) {
            return false;
        }

        return $this->flag($request->input('ActionSuccess'))
            && $this->flag($request->input('Authorized'))
            && $this->flag($request->input('Completed'))
            && $this->validPaymentIdentifiers($request);
    }


    private function looksLikeSuccessfulCallback(Request $request): bool
    {
        if ($this->isTerminalProviderEvent($request)) {
            return false;
        }

        // Authorization without capture is a valid intermediate event. Only a
        // callback claiming completion must contain the full success envelope.
        return $this->flag($request->input('Completed'));
    }


    private function validPaymentIdentifiers(Request $request): bool
    {
        return trim((string) $request->input('ApprovalCode', '')) !== ''
            && trim((string) $request->input('WsPayOrderId', '')) !== '';
    }


    private function hasUsableCredentials($paymentMethod): bool
    {
        return $paymentMethod
            && isset($paymentMethod->data->shop_id, $paymentMethod->data->secret_key)
            && trim((string) $paymentMethod->data->shop_id) !== ''
            && trim((string) $paymentMethod->data->secret_key) !== '';
    }


    private function credentialsForOrder(Order $order): ?array
    {
        if ($order->payment_attempt_started_at !== null) {
            $shopId = trim((string) $order->payment_attempt_merchant);
            $secretKey = app(PaymentAttemptService::class)->verificationSecret($order);

            return $shopId !== '' && $secretKey
                ? ['shop_id' => $shopId, 'secret_key' => $secretKey]
                : null;
        }

        $settings = Settings::get('payment', 'list.wspay');
        $paymentMethod = $settings instanceof Collection ? $settings->first() : null;

        if (! $this->hasUsableCredentials($paymentMethod)) {
            return null;
        }

        return [
            'shop_id' => trim((string) $paymentMethod->data->shop_id),
            'secret_key' => trim((string) $paymentMethod->data->secret_key),
        ];
    }


    private function legacyCallbackMayMutate(Order $order): bool
    {
        return (int) $order->order_status_id === (int) config('settings.order.status.unfinished')
            && $order->created_at !== null
            && $order->created_at->gte(now()->subDays(4));
    }


    private function providerEvent(Request $request, bool $success): string
    {
        if ($this->flag($request->input('Refunded'))) {
            return 'refund';
        }

        if ($this->flag($request->input('Reversed'))) {
            return 'reversal';
        }

        if ($this->flag($request->input('Voided'))) {
            return 'void';
        }

        if ($this->flag($request->input('Authorized')) && ! $this->flag($request->input('Completed'))) {
            return 'authorization';
        }

        if ($success || (string) $request->input('Success') === '1' || $this->successfulCallback($request)) {
            return 'payment';
        }

        return 'failure';
    }


    private function isTerminalStatus(int $status): bool
    {
        return in_array($status, [
            (int) config('settings.order.status.canceled'),
            (int) config('settings.order.status.declined'),
            (int) config('settings.order.status.returned'),
            (int) config('settings.order.status.refund'),
            (int) config('settings.order.status.blacklist'),
        ], true);
    }


    private function isTerminalProviderEvent(Request $request): bool
    {
        return $this->flag($request->input('Refunded'))
            || $this->flag($request->input('Reversed'))
            || $this->flag($request->input('Voided'));
    }


    private function hasPriorPayment(Order $order, Request $request): bool
    {
        $providerOrderId = trim((string) $request->input('WsPayOrderId', ''));

        if ($providerOrderId === '') {
            return false;
        }

        return Transaction::query()
            ->where('idempotency_key', $this->basePaymentKey($providerOrderId))
            ->where('order_id', $order->id)
            ->where('success', 1)
            ->exists();
    }


    private function hasDifferentPriorPayment(Order $order, string $providerOrderId): bool
    {
        if ($providerOrderId === '') {
            return false;
        }

        return Transaction::query()
            ->where('order_id', $order->id)
            ->where('success', 1)
            ->whereNotNull('pg_order_id')
            ->where('pg_order_id', '<>', $providerOrderId)
            ->exists();
    }


    private function basePaymentKey(string $providerOrderId): string
    {
        return hash('sha256', implode('|', ['wspay', 'provider', trim($providerOrderId)]));
    }


    /**
     * WSPay sends the ISO 4217 numeric code in callbacks, but accepting the
     * alphabetic representation keeps the check compatible with older payloads.
     *
     * @param mixed $currency
     */
    private function validCallbackCurrency($currency): bool
    {
        if ($currency === null || trim((string) $currency) === '') {
            return true;
        }

        return in_array(strtoupper(trim((string) $currency)), ['978', 'EUR'], true);
    }


    private function inventoryTransitionHandled(
        OrderInventoryService $inventory,
        Order $order,
        int $status
    ): bool
    {
        if (in_array($status, self::INVENTORY_HOLDING_STATUSES, true)) {
            return $inventory->isActive($order)
                && $order->inventory_committed_at !== null
                && $inventory->reservationMatchesOrder($order);
        }

        return true;
    }


    /**
     * @param Request $request
     *
     * @return string
     */
    private function callbackStatusLabel(Request $request): string
    {
        $parts = [];

        foreach (['ActionSuccess', 'Authorized', 'Completed', 'Voided', 'Refunded', 'Reversed'] as $key) {
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
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        return is_string($value) && strtolower($value) === 'true';
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


    private function validReferenceFormat($reference): bool
    {
        if (! is_string($reference) && ! is_int($reference)) {
            return false;
        }

        $reference = trim((string) $reference);

        return preg_match(self::RANDOM_REFERENCE_PATTERN, $reference) === 1
            || preg_match('/^\d+-\d{4}$/D', $reference) === 1;
    }


    /**
     * @param string|null $payment_plan
     *
     * @return int
     */
    private function installmentCount($payment_plan): int
    {
        if (! is_string($payment_plan) || $payment_plan === '') {
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
    private function signatureMeta($signature): array
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
     * @param Order       $order
     * @param string|null $amount
     *
     * @return bool
     */
    private function validResponseAmount(Order $order, ?string $amount): bool
    {
        $expected = $order->payment_expected_amount_minor !== null
            ? (int) $order->payment_expected_amount_minor
            : DecimalAmount::fromDatabase($order->total);

        if ($expected === null) {
            return false;
        }

        return $expected === $this->amountInMinorUnits($amount);
    }


    /**
     * WSPay returns major-unit decimal amounts. A separator-free value such as
     * "30" means EUR 30.00, not 30 cents.
     *
     * @param string|null $amount
     *
     * @return int|null
     */
    private function amountInMinorUnits(?string $amount): ?int
    {
        return DecimalAmount::fromMajorUnits($amount, false, true);
    }


    /**
     * @param string|null $amount
     * @param Order|null  $order
     *
     * @return string
     */
    private function decimalAmount(?string $amount, ?Order $order = null): string
    {
        if ( ! $amount) {
            return '0.00';
        }

        $minor = $this->amountInMinorUnits($amount);

        return $minor !== null ? DecimalAmount::format($minor) : '0.00';
    }


    private function validAttemptReference(Order $order, ?string $reference): bool
    {
        if ($order->payment_attempt_started_at === null) {
            return true;
        }

        return hash_equals('wspay', strtolower((string) $order->payment_attempt_provider))
            && hash_equals((string) $order->payment_attempt_reference, trim((string) $reference));
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
