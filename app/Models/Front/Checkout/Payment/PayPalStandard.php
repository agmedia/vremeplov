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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalStandard
{
    private const LIVE_BUSINESS = 'info@antiqueshop.hr';
    private const TEST_BUSINESS = 'tomislav-facilitator@agmedia.hr';
    private const LEGACY_CALLBACK_WINDOW_DAYS = 4;

    /** @var Order */
    private $order;

    /** @var string[] */
    private $formUrl = [
        'test' => 'https://www.sandbox.paypal.com/cgi-bin/webscr&pal=V4T754QB63XXL',
        'live' => 'https://www.paypal.com/cgi-bin/webscr&pal=V4T754QB63XXL',
    ];

    /** @var string[] */
    private $verificationUrl = [
        'test' => 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr',
        'live' => 'https://ipnpb.paypal.com/cgi-bin/webscr',
    ];

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function resolveFormView(?Collection $payment_method = null)
    {
        if (! $payment_method || ! $payment_method->count()) {
            return '';
        }

        $payment_method = $payment_method->first();
        $currentTestMode = (bool) data_get($payment_method, 'data.test', false);
        $currentBusiness = $this->expectedBusiness($currentTestMode);
        $this->order = app(PaymentAttemptService::class)->start(
            $this->order,
            'paypal',
            null,
            'EUR',
            $currentTestMode ? 'test' : 'live',
            $currentBusiness
        );

        $testMode = $this->order->payment_attempt_environment === 'test';
        $amountMinor = (int) $this->order->payment_expected_amount_minor;
        $orderId = (int) $this->order->id;
        $data = [
            'testmode' => $testMode,
            'business' => (string) $this->order->payment_attempt_merchant,
            'action' => $this->formUrl[$testMode ? 'test' : 'live'],
            'order_id' => (string) $this->order->payment_attempt_reference,
            // One aggregate line prevents per-item rounding from changing the
            // amount PayPal charges by one or two cents.
            'products' => [[
                'name' => 'Narudžba #' . $orderId,
                'model' => (string) $orderId,
                'price' => DecimalAmount::format($amountMinor),
                'quantity' => 1,
            ]],
            'discount_amount_cart' => 0,
            'currency' => 'EUR',
            'firstname' => $this->order->payment_fname,
            'lastname' => $this->order->payment_lname,
            'address' => $this->order->payment_address,
            'city' => $this->order->payment_city,
            'country' => $this->countryCode($this->order->payment_state),
            'postcode' => $this->order->payment_zip,
            'phone' => $this->order->payment_phone,
            'email' => $this->order->payment_email,
            'invoice' => $orderId . ' - ' . $this->order->payment_fname . ' ' . $this->order->payment_lname,
            'lc' => 'HR',
            // Do not rely only on the browser session after leaving the shop.
            // Some browsers do not return the checkout cookie from PayPal, so
            // carry the unguessable payment-attempt reference in the return URL.
            'return' => route('checkout.return.paypal', [
                'attempt' => (string) $this->order->payment_attempt_reference,
            ]),
            'rm' => 1,
            'notify_url' => route('checkout.notify.paypal'),
            'cancel_return' => route('kosarica'),
        ];

        return view('front.checkout.payment.paypal_standard', compact('data'));
    }

    /** Compatibility entry point for PaymentMethod. */
    public function finishOrder(Order $order, Request $request): bool
    {
        return $this->handleNotification($order, $request)['accepted'];
    }

    /** Verify the raw IPN before opening a database transaction or row lock. */
    public function handleNotification(Order $order, Request $request): array
    {
        $configuration = $this->configurationForOrder($order);

        if (! $configuration || ! $this->validNotificationEnvelope($order, $request, $configuration)) {
            Log::warning('PayPal IPN rejected by local validation.', [
                'order_id' => $order->id,
                'txn_id' => $this->safeTransactionReference($request),
                'payment_status' => $request->input('payment_status'),
            ]);

            return $this->notificationResult(false, 400, 'INVALID');
        }

        if (! $this->verifyNotification($request, $configuration['test_mode'])) {
            Log::warning('PayPal IPN was not verified by PayPal.', [
                'order_id' => $order->id,
                'txn_id' => $this->safeTransactionReference($request),
            ]);

            return $this->notificationResult(false, 400, 'INVALID');
        }

        $result = DB::transaction(function () use ($order, $request) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();
            $configuration = $lockedOrder ? $this->configurationForOrder($lockedOrder) : null;

            if (! $lockedOrder
                || ! $configuration
                || ! $this->validNotificationEnvelope($lockedOrder, $request, $configuration)) {
                return $this->notificationResult(false, 409, 'INVALID');
            }

            // Legacy rows stored PayerID rather than txn_id. A verified replay
            // for an already-recorded old order is acknowledged without mutation.
            if ($lockedOrder->payment_attempt_started_at === null
                && $this->hasLegacyPayPalTransaction($lockedOrder)) {
                return $this->notificationResult(true, 200, 'OK');
            }

            $status = (string) $request->input('payment_status');
            $txnId = trim((string) $request->input('txn_id'));

            if (! app(ProviderReferenceService::class)->claim($lockedOrder, 'paypal', $txnId)) {
                Log::critical('PayPal transaction ID was reused for another order.', [
                    'order_id' => $lockedOrder->id,
                    'txn_id' => $txnId,
                ]);

                return $this->notificationResult(false, 409, 'INVALID');
            }

            $idempotencyKey = $this->idempotencyKey($txnId, $status);
            $existing = Transaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ((int) $existing->order_id !== (int) $lockedOrder->id) {
                    Log::critical('PayPal transaction ID was reused for another order.', [
                        'order_id' => $lockedOrder->id,
                        'existing_order_id' => $existing->order_id,
                        'txn_id' => $txnId,
                    ]);

                    return $this->notificationResult(false, 409, 'INVALID');
                }

                return $this->completedRetryResult($lockedOrder, $status);
            }

            $inserted = DB::table('order_transactions')->insertOrIgnore(
                $this->transactionAttributes($lockedOrder, $request, $idempotencyKey)
            );

            if ($inserted !== 1) {
                $winner = Transaction::query()->where('idempotency_key', $idempotencyKey)->first();

                return $winner && (int) $winner->order_id === (int) $lockedOrder->id
                    ? $this->completedRetryResult($lockedOrder, $status)
                    : $this->notificationResult(false, 409, 'INVALID');
            }

            $shouldNotify = false;

            if ($status === 'Completed') {
                $shouldNotify = $this->completePayment($lockedOrder);
            } elseif (in_array($status, ['Denied', 'Failed', 'Expired', 'Voided'], true)) {
                $this->failUnpaidAttempt($lockedOrder, $status);
            } elseif (in_array($status, ['Refunded', 'Reversed'], true)) {
                $this->recordRefundOrReversal($lockedOrder, $request, $status);
            } elseif ($status === 'Canceled_Reversal') {
                $this->recordManualEvent($lockedOrder, 'PayPal canceled reversal requires manual review.');
            }

            $response = $this->notificationResult(true, 200, 'OK');
            $response['should_notify'] = $shouldNotify;

            return $response;
        });

        Log::info('PayPal IPN processed.', [
            'order_id' => $order->id,
            'txn_id' => $this->safeTransactionReference($request),
            'payment_status' => $request->input('payment_status'),
            'accepted' => $result['accepted'],
        ]);

        return $result;
    }

    /**
     * Apply a completed transaction obtained server-to-server from PayPal's
     * authenticated Reporting API. Browser return parameters never reach here.
     */
    public function handleReportingTransaction(
        Order $order,
        array $transactionInfo
    ): array {
        $error = $this->reportingValidationError($order, $transactionInfo);

        if ($error !== null) {
            return $this->notificationResult(false, 409, 'INVALID');
        }

        $result = DB::transaction(function () use ($order, $transactionInfo) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();
            if (! $lockedOrder
                || $this->reportingValidationError($lockedOrder, $transactionInfo) !== null) {
                return $this->notificationResult(false, 409, 'INVALID');
            }

            $txnId = trim((string) ($transactionInfo['transaction_id'] ?? ''));
            if (! app(ProviderReferenceService::class)->claim($lockedOrder, 'paypal', $txnId)) {
                Log::critical('PayPal Reporting transaction ID was reused for another order.', [
                    'order_id' => $lockedOrder->id,
                    'txn_id' => $txnId,
                ]);

                return $this->notificationResult(false, 409, 'INVALID');
            }

            $idempotencyKey = $this->idempotencyKey($txnId, 'Completed');
            $existing = Transaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return (int) $existing->order_id === (int) $lockedOrder->id
                    ? $this->completedRetryResult($lockedOrder, 'Completed')
                    : $this->notificationResult(false, 409, 'INVALID');
            }

            $amountMinor = DecimalAmount::fromMajorUnits(
                data_get($transactionInfo, 'transaction_amount.value')
            );
            $now = now();
            $inserted = DB::table('order_transactions')->insertOrIgnore([
                'order_id' => $lockedOrder->id,
                'success' => 1,
                'amount' => DecimalAmount::format((int) $amountMinor),
                'signature' => 'paypal-reporting-api',
                'payment_type' => 'reporting',
                'payment_plan' => null,
                'payment_partner' => 'PayPal',
                'provider_event' => 'paypal_completed',
                'datetime' => $this->transactionDateTime($transactionInfo['transaction_initiation_date'] ?? null),
                'approval_code' => null,
                'pg_order_id' => $txnId,
                'idempotency_key' => $idempotencyKey,
                'lang' => 'hr',
                'stan' => mb_substr((string) ($transactionInfo['invoice_id'] ?? ''), 0, 191) ?: null,
                'error' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted !== 1) {
                $winner = Transaction::query()->where('idempotency_key', $idempotencyKey)->first();

                return $winner && (int) $winner->order_id === (int) $lockedOrder->id
                    ? $this->completedRetryResult($lockedOrder, 'Completed')
                    : $this->notificationResult(false, 409, 'INVALID');
            }

            $result = $this->notificationResult(true, 200, 'OK');
            $result['should_notify'] = $this->completePayment(
                $lockedOrder,
                'paypal_reporting_completed'
            );

            return $result;
        });

        Log::info('PayPal Reporting transaction processed.', [
            'order_id' => $order->id,
            'txn_id' => trim((string) ($transactionInfo['transaction_id'] ?? '')),
            'accepted' => $result['accepted'],
        ]);

        return $result;
    }

    public function reportingValidationError(
        Order $order,
        array $transactionInfo
    ): ?string {
        $configuration = $this->configurationForOrder($order);
        if (! $configuration
            || strtolower(trim((string) $order->payment_code)) !== 'paypal'
            || strtolower((string) $order->payment_attempt_provider) !== 'paypal') {
            return 'Order is not a valid PayPal payment attempt.';
        }

        if (($transactionInfo['transaction_status'] ?? null) !== 'S') {
            return 'PayPal transaction is not successful.';
        }

        $custom = trim((string) ($transactionInfo['custom_field'] ?? ''));
        if (! preg_match('/^[a-f0-9]{64}$/D', $custom)
            || ! hash_equals((string) $order->payment_attempt_reference, $custom)) {
            return 'PayPal custom field does not match the payment attempt.';
        }

        $txnId = trim((string) ($transactionInfo['transaction_id'] ?? ''));
        if (! preg_match('/^[A-Za-z0-9._-]{1,191}$/D', $txnId)) {
            return 'PayPal transaction ID is invalid.';
        }

        $currency = strtoupper(trim((string) data_get($transactionInfo, 'transaction_amount.currency_code')));
        $amountMinor = DecimalAmount::fromMajorUnits(data_get($transactionInfo, 'transaction_amount.value'));
        if ($currency === ''
            || ! hash_equals(strtoupper($configuration['currency']), $currency)
            || $amountMinor === null
            || $amountMinor <= 0
            || ! in_array($amountMinor, $this->expectedAmounts($order), true)) {
            return 'PayPal amount or currency does not match.';
        }

        $invoice = trim((string) ($transactionInfo['invoice_id'] ?? ''));
        if (! preg_match('/^' . preg_quote((string) $order->id, '/') . '(?:\s*-\s*.+)?$/uD', $invoice)) {
            return 'PayPal invoice does not match the order.';
        }

        try {
            $paidAt = Carbon::parse((string) ($transactionInfo['transaction_initiation_date'] ?? ''));
        } catch (\Throwable $exception) {
            return 'PayPal transaction time is invalid.';
        }

        if ($order->payment_attempt_started_at === null || $paidAt->lt($order->payment_attempt_started_at)) {
            return 'PayPal transaction predates the payment attempt.';
        }

        return null;
    }

    public static function findNotificationOrder(string $custom): ?Order
    {
        $custom = trim($custom);

        if (preg_match('/^[a-f0-9]{64}$/D', $custom)) {
            return Order::query()
                ->where('payment_attempt_provider', 'paypal')
                ->where('payment_attempt_reference', $custom)
                ->first();
        }

        if (! ctype_digit($custom)) {
            return null;
        }

        return Order::query()
            ->whereKey((int) $custom)
            ->where('payment_code', 'paypal')
            ->whereNull('payment_attempt_started_at')
            ->where('created_at', '>=', now()->subDays(self::LEGACY_CALLBACK_WINDOW_DAYS))
            ->first();
    }

    /** Kept protected so tests can replace the verifier. */
    protected function verifyNotification(Request $request, bool $testMode): bool
    {
        $rawBody = $request->getContent();

        if ($rawBody === '' || strlen($rawBody) > 131072) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Vremeplov-PayPal-IPN/1.0',
                'Connection' => 'Close',
            ])->withOptions([
                'verify' => true,
                'allow_redirects' => false,
            ])->timeout(10)
                ->withBody(
                    'cmd=_notify-validate&' . $rawBody,
                    'application/x-www-form-urlencoded'
                )
                ->post($this->verificationUrl[$testMode ? 'test' : 'live']);

            return $response->successful() && trim($response->body()) === 'VERIFIED';
        } catch (\Throwable $exception) {
            Log::warning('PayPal IPN verification request failed.', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function completePayment(Order $order, string $inventoryReason = 'paypal_ipn_completed'): bool
    {
        $previousStatus = (int) $order->order_status_id;

        if ($order->payment_attempt_started_at !== null
            && ! app(PaymentAttemptService::class)->matchesSnapshot($order)) {
            $this->moveToManualResolution(
                $order,
                'PayPal payment completed after the frozen order contents changed; manual resolution is required.'
            );

            return false;
        }

        if ($this->isTerminalStatus($previousStatus)) {
            $this->flagPaymentReview(
                $order,
                'PayPal payment completed after a terminal order status; manual resolution is required.'
            );

            return false;
        }

        if (! $this->statusMustBePreserved($previousStatus)) {
            $order->update(['order_status_id' => config('settings.order.status.paid')]);
        }

        $order->refresh();

        // Never invent inventory movements for an old fulfilled order when a
        // legacy notification is replayed after deployment.
        if ($order->payment_attempt_started_at === null
            && $previousStatus !== (int) config('settings.order.status.unfinished')) {
            return false;
        }

        try {
            $inventory = app(OrderInventoryService::class);
            $managedOrder = $inventory->applyStatusTransition(
                $order,
                $previousStatus,
                (int) $order->order_status_id,
                $inventoryReason
            );

            if (! $inventory->isActive($managedOrder)
                || $managedOrder->inventory_committed_at === null
                || ! $inventory->reservationMatchesOrder($managedOrder)) {
                throw new \RuntimeException(
                    'PayPal payment was confirmed without a committed inventory allocation.'
                );
            }

            return true;
        } catch (\Throwable $exception) {
            app(OrderInventoryService::class)->recordAllocationError($order, $exception);
            $this->moveToManualResolution($order, $exception->getMessage());

            return false;
        }
    }

    private function failUnpaidAttempt(Order $order, string $status): void
    {
        $wasPaid = Transaction::query()
            ->where('order_id', $order->id)
            ->where('payment_partner', 'PayPal')
            ->where('provider_event', 'paypal_completed')
            ->where('success', 1)
            ->exists();

        if ($wasPaid || ! in_array((int) $order->order_status_id, [
            (int) config('settings.order.status.unfinished'),
            (int) config('settings.order.status.new'),
        ], true)) {
            $this->recordManualEvent($order, 'PayPal ' . $status . ' received after order processing; status preserved.');

            return;
        }

        $previousStatus = (int) $order->order_status_id;
        $order->update(['order_status_id' => config('settings.order.status.declined')]);
        $fresh = $order->fresh();
        $inventory = app(OrderInventoryService::class);

        if ($inventory->isActive($fresh) && $fresh->inventory_committed_at === null) {
            $inventory->release($fresh, 'paypal_ipn_' . strtolower($status));
        } else {
            $inventory->applyStatusTransition(
                $fresh,
                $previousStatus,
                (int) config('settings.order.status.declined'),
                'paypal_ipn_' . strtolower($status)
            );
        }

        $this->insertHistory($order, (int) config('settings.order.status.declined'), 'PayPal ' . $status);
    }

    private function recordRefundOrReversal(Order $order, Request $request, string $status): void
    {
        $parentTxnId = trim((string) $request->input('parent_txn_id', ''));
        $parentBelongsToOrder = $parentTxnId !== ''
            && Transaction::query()
                ->where('order_id', $order->id)
                ->where('payment_partner', 'PayPal')
                ->where('provider_event', 'paypal_completed')
                ->where('pg_order_id', $parentTxnId)
                ->where('success', 1)
                ->exists();
        $amountMinor = $this->amountInCents($request->input('mc_gross'), true);
        $fullAmount = $amountMinor !== null
            && $amountMinor < 0
            && in_array(abs($amountMinor), $this->expectedAmounts($order), true);

        if (! $parentBelongsToOrder || ! $fullAmount) {
            $this->recordManualEvent(
                $order,
                'PayPal ' . $status . ' recorded (partial or unmatched parent transaction); manual review required.'
            );

            return;
        }

        $order->update(['order_status_id' => config('settings.order.status.refund')]);
        $fresh = $order->fresh();
        $inventory = app(OrderInventoryService::class);

        // A financial refund does not prove that a physical book was returned.
        if ($inventory->isActive($fresh) && $fresh->inventory_committed_at === null) {
            $inventory->release($fresh, 'paypal_ipn_' . strtolower($status));
        }

        $this->insertHistory(
            $order,
            (int) config('settings.order.status.refund'),
            'PayPal ' . $status . ' (zaliha nije automatski vraćena)'
        );
    }

    private function moveToManualResolution(Order $order, string $message): void
    {
        $manualStatus = (int) config('settings.order.status.call_when_found');
        $message = mb_substr($message, 0, 500);
        $order->forceFill([
            'order_status_id' => $manualStatus,
            'inventory_allocation_error' => $message,
        ])->saveOrFail();
        $this->insertHistory($order, $manualStatus, 'PayPal: ' . $message);

        Log::critical('PayPal payment requires manual order resolution.', [
            'order_id' => $order->id,
            'error' => $message,
        ]);
    }

    private function recordManualEvent(Order $order, string $message): void
    {
        $this->flagPaymentReview($order, $message);
    }

    private function flagPaymentReview(Order $order, string $message): void
    {
        $message = mb_substr($message, 0, 500);
        $order->forceFill(['payment_review_error' => $message])->saveOrFail();
        $this->insertHistory($order, (int) $order->order_status_id, $message);
        Log::warning($message, ['order_id' => $order->id]);
    }

    private function insertHistory(Order $order, int $status, string $comment): void
    {
        OrderHistory::query()->insert([
            'order_id' => $order->id,
            'user_id' => 0,
            'status' => $status,
            'comment' => mb_substr($comment, 0, 500),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function transactionAttributes(Order $order, Request $request, string $idempotencyKey): array
    {
        $status = (string) $request->input('payment_status');
        $amountMinor = $this->amountInCents($request->input('mc_gross'), true) ?: 0;
        $now = now();

        return [
            'order_id' => $order->id,
            'success' => $status === 'Completed' ? 1 : 0,
            'amount' => DecimalAmount::format($amountMinor),
            'signature' => mb_substr((string) $request->input('verify_sign', ''), 0, 191),
            'payment_type' => mb_substr((string) $request->input('payment_type', ''), 0, 16) ?: null,
            'payment_plan' => null,
            'payment_partner' => 'PayPal',
            'provider_event' => 'paypal_' . strtolower($status),
            'datetime' => $this->transactionDateTime($request->input('payment_date')),
            'approval_code' => mb_substr((string) $request->input('parent_txn_id', ''), 0, 191) ?: null,
            'pg_order_id' => trim((string) $request->input('txn_id')),
            'idempotency_key' => $idempotencyKey,
            'lang' => 'hr',
            'stan' => mb_substr((string) $request->input('invoice', ''), 0, 191) ?: null,
            'error' => $status === 'Completed'
                ? ''
                : mb_substr($status . ': ' . (string) $request->input('reason_code', ''), 0, 191),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function validNotificationEnvelope(Order $order, Request $request, array $configuration): bool
    {
        if (! hash_equals('paypal', strtolower(trim((string) $order->payment_code)))) {
            return false;
        }

        $custom = trim((string) $request->input('custom', ''));

        if ($order->payment_attempt_started_at !== null) {
            if (! hash_equals('paypal', strtolower((string) $order->payment_attempt_provider))
                || ! hash_equals((string) $order->payment_attempt_reference, $custom)) {
                return false;
            }
        } elseif (! ctype_digit($custom) || (int) $custom !== (int) $order->id) {
            return false;
        }

        $status = (string) $request->input('payment_status', '');

        if (! in_array($status, [
            'Completed', 'Pending', 'Processed', 'Denied', 'Failed', 'Expired', 'Voided',
            'Refunded', 'Reversed', 'Canceled_Reversal',
        ], true)) {
            return false;
        }

        $txnId = trim((string) $request->input('txn_id', ''));

        if (! preg_match('/^[A-Za-z0-9._-]{1,191}$/D', $txnId)
            || ! hash_equals(
                strtoupper($configuration['currency']),
                strtoupper(trim((string) $request->input('mc_currency', '')))
            )
            || ! $this->validReceiver($request, $configuration['merchant'])) {
            return false;
        }

        $amountMinor = $this->amountInCents($request->input('mc_gross'), true);

        if ($status === 'Completed') {
            return $amountMinor !== null
                && $amountMinor > 0
                && in_array($amountMinor, $this->expectedAmounts($order), true);
        }

        if (in_array($status, ['Refunded', 'Reversed'], true)) {
            return $amountMinor !== null
                && $amountMinor < 0
                && preg_match(
                    '/^[A-Za-z0-9._-]{1,191}$/D',
                    trim((string) $request->input('parent_txn_id', ''))
                );
        }

        return $amountMinor === null || $amountMinor >= 0;
    }

    private function expectedAmounts(Order $order): array
    {
        if ($order->payment_expected_amount_minor !== null) {
            return [(int) $order->payment_expected_amount_minor];
        }

        $totalMinor = DecimalAmount::fromDatabase($order->total);

        if ($totalMinor === null) {
            return [];
        }

        $amounts = [$totalMinor];
        $subtotal = $order->totals()->where('code', 'subtotal')->value('value');

        if ($subtotal !== null) {
            $legacy = 0;
            $valid = true;

            foreach ($order->products()->get() as $item) {
                $itemMinor = DecimalAmount::fromDatabase($item->price);

                if ($itemMinor === null) {
                    $valid = false;
                    break;
                }

                $legacy += $itemMinor * (int) $item->quantity;
            }

            $difference = DecimalAmount::databaseDifference($order->total, $subtotal);

            if ($valid && $difference !== null) {
                $amounts[] = $legacy + $difference;
            }
        }

        return array_values(array_unique($amounts));
    }

    private function validReceiver(Request $request, string $expected): bool
    {
        $receiver = mb_strtolower(trim((string) $request->input('receiver_email', '')));
        $expected = mb_strtolower(trim($expected));

        return $receiver !== '' && hash_equals($expected, $receiver);
    }

    private function configurationForOrder(Order $order): ?array
    {
        if ($order->payment_attempt_started_at !== null) {
            if ($order->payment_attempt_environment === null
                || $order->payment_attempt_merchant === null
                || $order->payment_expected_currency === null) {
                return null;
            }

            return [
                'test_mode' => $order->payment_attempt_environment === 'test',
                'merchant' => (string) $order->payment_attempt_merchant,
                'currency' => (string) $order->payment_expected_currency,
            ];
        }

        $settings = Settings::get('payment', 'list.paypal');
        $configuration = $settings instanceof Collection ? $settings->first() : null;

        if (! $configuration) {
            return null;
        }

        $testMode = (bool) data_get($configuration, 'data.test', false);

        return [
            'test_mode' => $testMode,
            'merchant' => $this->expectedBusiness($testMode),
            'currency' => 'EUR',
        ];
    }

    private function expectedBusiness(bool $testMode): string
    {
        return $testMode ? self::TEST_BUSINESS : self::LIVE_BUSINESS;
    }

    private function idempotencyKey(string $txnId, string $status): string
    {
        return hash('sha256', 'paypal|txn|' . $txnId . '|status|' . strtolower($status));
    }

    private function statusMustBePreserved(int $status): bool
    {
        return in_array($status, array_merge(
            (array) config('settings.order.paid_statuses', []),
            [
                (int) config('settings.order.status.call_when_found'),
                (int) config('settings.order.status.blacklist'),
            ]
        ), true);
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

    private function hasLegacyPayPalTransaction(Order $order): bool
    {
        return Transaction::query()
            ->where('order_id', $order->id)
            ->whereRaw('LOWER(payment_partner) = ?', ['paypal'])
            ->exists();
    }

    private function amountInCents($amount, bool $allowNegative = false): ?int
    {
        return DecimalAmount::fromMajorUnits($amount, $allowNegative);
    }

    private function transactionDateTime($value): string
    {
        if ($value) {
            try {
                return Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
            } catch (\Throwable $exception) {
                // Keep an otherwise verified notification.
            }
        }

        return Carbon::now()->format('Y-m-d H:i:s');
    }

    private function safeTransactionReference(Request $request): ?string
    {
        $txnId = trim((string) $request->input('txn_id', ''));

        return preg_match('/^[A-Za-z0-9._-]{1,191}$/D', $txnId) ? $txnId : null;
    }

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
            Log::warning('PayPal country code lookup failed.', [
                'country' => $country,
                'error' => $exception->getMessage(),
            ]);
        }

        return 'HR';
    }

    private function notificationResult(bool $accepted, int $httpStatus, string $message): array
    {
        return [
            'accepted' => $accepted,
            'http_status' => $httpStatus,
            'message' => $message,
            'should_notify' => false,
        ];
    }

    private function completedRetryResult(Order $order, string $status): array
    {
        $result = $this->notificationResult(true, 200, 'OK');
        $result['should_notify'] = $status === 'Completed'
            && $order->inventory_committed_at !== null
            && $order->inventory_released_at === null
            && ! $order->inventory_allocation_error
            && ! $order->payment_review_error;

        return $result;
    }
}
