<?php

namespace Tests\Feature;

use App\Models\Back\Orders\Order;
use App\Models\Front\Checkout\Payment\Wspay;
use App\Services\Inventory\OrderInventoryService;
use App\Services\Payments\PaymentAttemptService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WspayHardeningTest extends TestCase
{
    private const SHOP_ID = 'TEST-SHOP';
    private const SECRET = 'test-secret';

    /** @var string */
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'wspay_testing',
            'database.connections.wspay_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge('wspay_testing');
        $this->createSchema();
        $this->storeWspaySettings();
        $this->runPaymentAttemptMigration();
        $this->runProviderReferenceMigration();
    }

    protected function tearDown(): void
    {
        DB::purge('wspay_testing');
        config(['database.default' => $this->originalConnection]);

        parent::tearDown();
    }

    public function test_browser_return_with_invalid_signature_cannot_change_order_or_create_transaction(): void
    {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder());
        $request = new Request([
            'ShoppingCartID' => $order->payment_attempt_reference,
            'Success' => '1',
            'ApprovalCode' => 'APPROVED-1',
            'WsPayOrderId' => 'wspay-payment-1',
            'Amount' => '30,00',
            'Signature' => 'forged-signature',
        ]);

        $finished = (new Wspay($order))->finishOrder($order, $request);

        $this->assertFalse($finished);
        $this->assertSame(
            config('settings.order.status.unfinished'),
            (int) DB::table('orders')->where('id', $order->id)->value('order_status_id')
        );
        $this->assertSame(0, DB::table('order_transactions')->count());
    }

    public function test_signed_browser_return_with_wrong_amount_is_rejected(): void
    {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder());
        $payload = [
            'ShoppingCartID' => $order->payment_attempt_reference,
            'Success' => '1',
            'ApprovalCode' => 'APPROVED-WRONG-AMOUNT',
            'WsPayOrderId' => 'wspay-payment-wrong-amount',
            'Amount' => '29,99',
        ];
        $payload['Signature'] = $this->browserSignature($payload);

        $finished = (new Wspay($order))->finishOrder($order, new Request($payload));

        $this->assertFalse($finished);
        $this->assertSame(
            config('settings.order.status.unfinished'),
            (int) $order->fresh()->order_status_id
        );
        $this->assertSame(0, DB::table('order_transactions')->count());
    }

    public function test_callback_and_browser_return_create_one_transaction_for_same_provider_payment(): void
    {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder(true), true);
        $provider_order_id = 'wspay-payment-2';
        $shopping_cart_id = $order->payment_attempt_reference;
        $approval_code = 'APPROVED-2';

        $callback = [
            'ShoppingCartID' => $shopping_cart_id,
            'ShopID' => self::SHOP_ID,
            'ActionSuccess' => '1',
            'Authorized' => '1',
            'Completed' => '1',
            'ApprovalCode' => $approval_code,
            'WsPayOrderId' => $provider_order_id,
            'Amount' => '30,00',
            'CreditCardName' => 'MASTERCARD',
            'PaymentPlan' => '0000',
            'TransactionDateTime' => '20260903120000',
            'STAN' => '123456',
        ];
        $callback['Signature'] = $this->callbackSignature($callback);

        $callback_result = (new Wspay(new Order()))->handleCallback(new Request($callback));

        $this->assertTrue($callback_result['success']);
        $this->assertTrue($callback_result['inventory_allocated']);
        $this->assertSame(config('settings.order.status.paid'), $callback_result['status_id']);
        $this->assertSame(0, (int) DB::table('products')->value('quantity'));
        $this->assertSame(1, DB::table('inventory_movements')->where('action', 'reserve')->count());
        $this->assertNotNull($order->fresh()->inventory_committed_at);

        $browser = [
            'ShoppingCartID' => $shopping_cart_id,
            'Success' => '1',
            'ApprovalCode' => $approval_code,
            'WsPayOrderId' => $provider_order_id,
            'Amount' => '30,00',
            'PaymentType' => 'BROWSER-VISA',
            'DateTime' => '20260903120001',
            'STAN' => '123456',
        ];
        $browser['Signature'] = $this->browserSignature($browser);

        $this->assertTrue((new Wspay($order->fresh()))->finishOrder($order->fresh(), new Request($browser)));

        $transactions = DB::table('order_transactions')
                          ->where('order_id', $order->id)
                          ->where('pg_order_id', $provider_order_id)
                          ->get();

        $this->assertCount(1, $transactions);
        $this->assertSame('MASTERCARD', $transactions->first()->payment_type);
        $this->assertEquals(30.00, (float) $transactions->first()->amount);
        $this->assertSame(0, (int) DB::table('products')->value('quantity'));
    }

    public function test_callback_updates_an_existing_browser_transaction_without_duplicating_it(): void
    {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder(true), true);
        $provider_order_id = 'wspay-payment-3';
        $shopping_cart_id = $order->payment_attempt_reference;
        $approval_code = 'APPROVED-3';
        $browser = [
            'ShoppingCartID' => $shopping_cart_id,
            'Success' => '1',
            'ApprovalCode' => $approval_code,
            'WsPayOrderId' => $provider_order_id,
            'Amount' => '30,00',
            'PaymentType' => 'VISA',
        ];
        $browser['Signature'] = $this->browserSignature($browser);

        $this->assertTrue((new Wspay($order))->finishOrder($order, new Request($browser)));

        $callback = [
            'ShoppingCartID' => $shopping_cart_id,
            'ShopID' => self::SHOP_ID,
            'ActionSuccess' => '1',
            'Authorized' => '1',
            'Completed' => '1',
            'ApprovalCode' => $approval_code,
            'WsPayOrderId' => $provider_order_id,
            'Amount' => '30,00',
            'CreditCardName' => 'MASTERCARD',
            'PaymentPlan' => '0200',
        ];
        $callback['Signature'] = $this->callbackSignature($callback);

        $result = (new Wspay(new Order()))->handleCallback(new Request($callback));
        $transaction = DB::table('order_transactions')
                         ->where('order_id', $order->id)
                         ->where('pg_order_id', $provider_order_id)
                         ->first();

        $this->assertTrue($result['success']);
        $this->assertSame(1, DB::table('order_transactions')->where('order_id', $order->id)->count());
        $this->assertSame('MASTERCARD', $transaction->payment_type);
        $this->assertSame('0200', $transaction->payment_plan);
    }

    public function test_success_replays_do_not_move_a_shipped_order_back_to_paid(): void
    {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder(true), true);
        $provider_order_id = 'wspay-payment-shipped';
        $approval_code = 'APPROVED-SHIPPED';
        $callback = $this->successfulCallbackPayload(
            $order,
            $provider_order_id,
            $approval_code,
            '30'
        );

        $initial_result = (new Wspay(new Order()))->handleCallback(new Request($callback));

        $this->assertTrue($initial_result['success']);
        $this->assertSame(0, (int) DB::table('products')->value('quantity'));
        $this->assertNotNull($order->fresh()->inventory_committed_at);

        $order->update(['order_status_id' => config('settings.order.status.send')]);

        $callback_result = (new Wspay(new Order()))->handleCallback(new Request($callback));

        $this->assertTrue($callback_result['success']);
        $this->assertTrue($callback_result['inventory_allocated']);
        $this->assertSame(config('settings.order.status.send'), $callback_result['status_id']);
        $this->assertSame(0, (int) DB::table('products')->value('quantity'));

        $browser = [
            'ShoppingCartID' => $order->payment_attempt_reference,
            'Success' => '1',
            'ApprovalCode' => $approval_code,
            'WsPayOrderId' => $provider_order_id,
            'Amount' => '30,00',
        ];
        $browser['Signature'] = $this->browserSignature($browser);

        $this->assertTrue((new Wspay($order->fresh()))->finishOrder($order->fresh(), new Request($browser)));
        $this->assertSame(config('settings.order.status.send'), (int) $order->fresh()->order_status_id);
        $this->assertSame(1, DB::table('order_transactions')->where('order_id', $order->id)->count());
    }

    public function test_signed_success_callback_with_wrong_amount_is_rejected_before_any_change(): void
    {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder(true), true);
        $payload = [
            'ShoppingCartID' => $order->payment_attempt_reference,
            'ShopID' => self::SHOP_ID,
            'ActionSuccess' => '1',
            'Authorized' => '1',
            'Completed' => '1',
            'ApprovalCode' => 'APPROVED-WRONG-CALLBACK-AMOUNT',
            'WsPayOrderId' => 'wspay-wrong-callback-amount',
            'Amount' => '29,99',
            'CurrencyCode' => '978',
        ];
        $payload['Signature'] = $this->callbackSignature($payload);

        $result = (new Wspay(new Order()))->handleCallback(new Request($payload));

        $this->assertFalse($result['success']);
        $this->assertSame(422, $result['http_status']);
        $this->assertSame(config('settings.order.status.unfinished'), (int) $order->fresh()->order_status_id);
        $this->assertSame(0, (int) DB::table('products')->value('quantity'));
        $this->assertNull($order->fresh()->inventory_committed_at);
        $this->assertNull($order->fresh()->inventory_released_at);
        $this->assertSame(0, DB::table('order_transactions')->count());
        $this->assertSame(0, DB::table('order_history')->count());
        $this->assertSame(1, DB::table('inventory_movements')->where('action', 'reserve')->count());
        $this->assertSame(0, DB::table('inventory_movements')->where('action', 'release')->count());
    }

    public function test_signed_success_callback_with_wrong_currency_is_rejected(): void
    {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder(true), true);
        $payload = [
            'ShoppingCartID' => $order->payment_attempt_reference,
            'ShopID' => self::SHOP_ID,
            'ActionSuccess' => '1',
            'Authorized' => '1',
            'Completed' => '1',
            'ApprovalCode' => 'APPROVED-WRONG-CURRENCY',
            'WsPayOrderId' => 'wspay-wrong-currency',
            'Amount' => '30,00',
            'CurrencyCode' => '840',
        ];
        $payload['Signature'] = $this->callbackSignature($payload);

        $result = (new Wspay(new Order()))->handleCallback(new Request($payload));

        $this->assertFalse($result['success']);
        $this->assertSame(422, $result['http_status']);
        $this->assertSame(config('settings.order.status.unfinished'), (int) $order->fresh()->order_status_id);
        $this->assertSame(0, DB::table('order_transactions')->count());
    }

    public function test_paid_callback_without_stock_is_kept_for_manual_resolution(): void
    {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder(true, 0));
        $payload = [
            'ShoppingCartID' => $order->payment_attempt_reference,
            'ShopID' => self::SHOP_ID,
            'ActionSuccess' => '1',
            'Authorized' => '1',
            'Completed' => '1',
            'ApprovalCode' => 'APPROVED-NO-STOCK',
            'WsPayOrderId' => 'wspay-no-stock',
            'Amount' => '30,00',
            'CurrencyCode' => 'EUR',
        ];
        $payload['Signature'] = $this->callbackSignature($payload);

        $result = (new Wspay(new Order()))->handleCallback(new Request($payload));
        $fresh_order = $order->fresh();

        $this->assertFalse($result['success']);
        $this->assertFalse($result['inventory_allocated']);
        $this->assertSame(200, $result['http_status']);
        $this->assertSame(config('settings.order.status.call_when_found'), $result['status_id']);
        $this->assertSame(config('settings.order.status.call_when_found'), (int) $fresh_order->order_status_id);
        $this->assertNotNull($fresh_order->inventory_allocation_error);
        $this->assertSame(1, DB::table('order_transactions')->where('order_id', $order->id)->count());
        $this->assertSame(
            [config('settings.order.status.paid'), config('settings.order.status.call_when_found')],
            DB::table('order_history')->where('order_id', $order->id)->orderBy('id')->pluck('status')->all()
        );
        $this->assertSame(0, DB::table('inventory_movements')->count());

        $retry = (new Wspay(new Order()))->handleCallback(new Request($payload));

        $this->assertFalse($retry['success']);
        $this->assertFalse($retry['inventory_allocated']);
        $this->assertSame(config('settings.order.status.call_when_found'), $retry['status_id']);
        $this->assertNotEmpty($retry['inventory_error']);
        $this->assertNotNull($order->fresh()->inventory_allocation_error);
        $this->assertSame(1, DB::table('order_transactions')->where('order_id', $order->id)->count());
    }

    public function test_success_without_provider_id_is_rejected(): void
    {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder());
        $payload = [
            'ShoppingCartID' => $order->payment_attempt_reference,
            'Success' => '1',
            'ApprovalCode' => 'APPROVED-WITHOUT-PROVIDER-ID',
            'Amount' => '30,00',
        ];
        $payload['Signature'] = $this->browserSignature($payload);

        $this->assertFalse(
            (new Wspay($order))->finishOrder($order, new Request($payload))
        );
        $this->assertSame(config('settings.order.status.unfinished'), (int) $order->fresh()->order_status_id);
        $this->assertSame(0, DB::table('order_transactions')->count());
    }

    /**
     * @dataProvider majorUnitAmountProvider
     */
    public function test_wspay_amount_is_parsed_as_a_major_unit_decimal(string $amount): void
    {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder());
        $payload = $this->browserPayload(
            $order,
            'wspay-major-' . md5($amount),
            'APPROVED-MAJOR-' . md5($amount),
            $amount
        );

        $this->assertTrue((new Wspay($order))->finishOrder($order, new Request($payload)));
        $this->assertEquals(
            30.00,
            (float) DB::table('order_transactions')->where('order_id', $order->id)->value('amount')
        );
    }

    public function majorUnitAmountProvider(): array
    {
        return [
            'integer major units' => ['30'],
            'decimal point' => ['30.00'],
            'decimal comma' => ['30,00'],
        ];
    }

    public function test_separator_free_3000_means_three_thousand_euro_not_thirty_euro(): void
    {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder());
        $payload = $this->browserPayload(
            $order,
            'wspay-not-cents',
            'APPROVED-NOT-CENTS',
            '3000'
        );

        $this->assertFalse((new Wspay($order))->finishOrder($order, new Request($payload)));
        $this->assertSame(config('settings.order.status.unfinished'), (int) $order->fresh()->order_status_id);
        $this->assertSame(0, DB::table('order_transactions')->count());
    }

    public function test_oversized_amount_is_rejected_without_throwing_or_mutating_the_order(): void
    {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder());
        $payload = $this->browserPayload(
            $order,
            'wspay-overflow',
            'APPROVED-OVERFLOW',
            str_repeat('9', 80)
        );

        $this->assertFalse((new Wspay($order))->finishOrder($order, new Request($payload)));
        $this->assertSame(config('settings.order.status.unfinished'), (int) $order->fresh()->order_status_id);
        $this->assertSame(0, DB::table('order_transactions')->count());
    }

    public function test_callback_requires_the_shop_id_even_when_the_hash_itself_is_valid(): void
    {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder(true), true);
        $payload = $this->successfulCallbackPayload(
            $order,
            'wspay-missing-shop',
            'APPROVED-MISSING-SHOP'
        );
        unset($payload['ShopID']);

        $result = (new Wspay(new Order()))->handleCallback(new Request($payload));

        $this->assertFalse($result['success']);
        $this->assertSame(422, $result['http_status']);
        $this->assertSame(config('settings.order.status.unfinished'), (int) $order->fresh()->order_status_id);
        $this->assertNull($order->fresh()->inventory_committed_at);
        $this->assertSame(0, DB::table('order_transactions')->count());
    }

    public function test_authorization_only_callback_does_not_complete_or_commit_the_order(): void
    {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder(true), true);
        $payload = $this->successfulCallbackPayload(
            $order,
            'wspay-authorization-only',
            'APPROVED-AUTHORIZATION-ONLY'
        );
        $payload['Completed'] = '0';
        $payload['Signature'] = $this->callbackSignature($payload);

        $result = (new Wspay(new Order()))->handleCallback(new Request($payload));

        $this->assertTrue($result['success']);
        $this->assertSame(200, $result['http_status']);
        $this->assertFalse($result['payment_completed']);
        $this->assertSame(config('settings.order.status.unfinished'), (int) $order->fresh()->order_status_id);
        $this->assertNull($order->fresh()->inventory_committed_at);
        $this->assertSame(1, DB::table('order_transactions')->count());
        $this->assertSame('authorization', DB::table('order_transactions')->value('provider_event'));
        $this->assertSame(0, (int) DB::table('order_transactions')->value('success'));
        $this->assertSame(0, (int) DB::table('products')->value('quantity'));
    }

    public function test_callback_uses_credentials_frozen_when_the_payment_attempt_started(): void
    {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder(true), true);

        $this->storeWspaySettings('ROTATED-SHOP', 'rotated-secret', true);

        $payload = $this->successfulCallbackPayload(
            $order,
            'wspay-frozen-credentials',
            'APPROVED-FROZEN-CREDENTIALS'
        );
        $result = (new Wspay(new Order()))->handleCallback(new Request($payload));

        $this->assertTrue($result['success']);
        $this->assertSame(config('settings.order.status.paid'), (int) $order->fresh()->order_status_id);
        $this->assertSame(0, (int) DB::table('products')->value('quantity'));
        $this->assertSame(self::SHOP_ID, $order->fresh()->payment_attempt_merchant);
        $this->assertSame(self::SECRET, app(PaymentAttemptService::class)->verificationSecret($order->fresh()));
    }

    /**
     * @dataProvider terminalEventAndCommittedStatusProvider
     */
    public function test_terminal_events_never_restock_a_committed_order_and_require_review(
        string $flag,
        int $status
    ): void {
        $this->runTransactionMigration();
        $suffix = strtolower($flag) . '-' . $status;
        $order = $this->startAttempt($this->createOrder(true), true);
        $providerOrderId = 'wspay-terminal-' . $suffix;
        $approvalCode = 'APPROVED-TERMINAL-' . strtoupper($suffix);
        $payment = $this->successfulCallbackPayload($order, $providerOrderId, $approvalCode);

        $paid = (new Wspay(new Order()))->handleCallback(new Request($payment));
        $this->assertTrue($paid['success']);
        $this->assertSame(0, (int) DB::table('products')->value('quantity'));
        $this->assertNotNull($order->fresh()->inventory_committed_at);

        $order->update([
            'order_status_id' => $status,
            'payment_review_error' => null,
        ]);
        $terminal = $this->terminalCallbackPayload($order, $providerOrderId, $approvalCode, $flag);

        $first = (new Wspay(new Order()))->handleCallback(new Request($terminal));
        $second = (new Wspay(new Order()))->handleCallback(new Request($terminal));
        $fresh = $order->fresh();

        $this->assertSame($status, (int) $fresh->order_status_id);
        $this->assertSame(0, (int) DB::table('products')->value('quantity'));
        $this->assertNotNull($fresh->inventory_committed_at);
        $this->assertNull($fresh->inventory_released_at);
        $this->assertSame(0, DB::table('inventory_movements')->where('action', 'release')->count());
        $this->assertSame(2, DB::table('order_transactions')->where('order_id', $order->id)->count());
        $this->assertSame(2, DB::table('order_history')->where('order_id', $order->id)->count());
        $this->assertNotNull($fresh->payment_review_error);
        $this->assertFalse($first['success']);
        $this->assertFalse($second['success']);
    }

    public function terminalEventAndCommittedStatusProvider(): array
    {
        $cases = [];

        foreach (['Voided', 'Refunded', 'Reversed'] as $flag) {
            foreach ([
                'paid' => 3,
                'shipped' => 4,
                'ready' => 10,
                'finished' => 9,
            ] as $label => $status) {
                $cases[strtolower($flag) . ' after ' . $label] = [$flag, $status];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider terminalEventProvider
     */
    public function test_terminal_event_cannot_release_an_uncommitted_reservation_before_normal_expiry(
        string $flag
    ): void {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder(true), true);
        $providerOrderId = 'wspay-uncommitted-' . strtolower($flag);
        $approvalCode = 'APPROVED-UNCOMMITTED-' . strtoupper($flag);
        $this->insertCanonicalPayment($order, $providerOrderId, $approvalCode);
        $terminal = $this->terminalCallbackPayload($order, $providerOrderId, $approvalCode, $flag);

        $result = (new Wspay(new Order()))->handleCallback(new Request($terminal));
        $fresh = $order->fresh();

        $this->assertSame(config('settings.order.status.unfinished'), (int) $fresh->order_status_id);
        $this->assertSame(0, (int) DB::table('products')->value('quantity'));
        $this->assertNull($fresh->inventory_released_at);
        $this->assertNotNull($fresh->payment_review_error);
        $this->assertFalse($result['success']);

        $this->travel(31)->minutes();

        $this->assertSame(1, app(OrderInventoryService::class)->expireReservations());
        $this->assertSame(1, (int) DB::table('products')->value('quantity'));
        $this->assertNotNull($order->fresh()->inventory_released_at);
        $this->assertSame(0, app(OrderInventoryService::class)->expireReservations());
    }

    public function terminalEventProvider(): array
    {
        return [
            'void' => ['Voided'],
            'refund' => ['Refunded'],
            'reversal' => ['Reversed'],
        ];
    }

    public function test_provider_payment_identifier_cannot_be_reassigned_to_another_order(): void
    {
        $this->runTransactionMigration();
        $firstOrder = $this->startAttempt($this->createOrder());
        $secondOrder = $this->startAttempt($this->createOrder());
        $providerOrderId = 'wspay-global-owner';
        $approvalCode = 'APPROVED-GLOBAL-OWNER';
        $firstPayload = $this->successfulCallbackPayload($firstOrder, $providerOrderId, $approvalCode);

        $first = (new Wspay(new Order()))->handleCallback(new Request($firstPayload));
        $this->assertTrue($first['success']);

        // ShoppingCartID and Amount are not covered by WSPay's callback hash. A
        // captured callback must still not be usable as a confused deputy.
        $replayedPayload = $firstPayload;
        $replayedPayload['ShoppingCartID'] = $secondOrder->payment_attempt_reference;
        $second = (new Wspay(new Order()))->handleCallback(new Request($replayedPayload));

        $this->assertFalse($second['success']);
        $this->assertSame(409, $second['http_status']);
        $this->assertSame(config('settings.order.status.unfinished'), (int) $secondOrder->fresh()->order_status_id);
        $this->assertSame(1, DB::table('order_transactions')->where('pg_order_id', $providerOrderId)->count());
        $this->assertSame(1, DB::table('payment_provider_references')
            ->where('provider', 'wspay')
            ->where('reference', $providerOrderId)
            ->where('order_id', $firstOrder->id)
            ->count());
    }

    public function test_new_payment_attempt_uses_a_unique_random_wspay_reference(): void
    {
        $firstOrder = $this->createOrder();
        $paymentMethod = collect([(object) [
            'data' => (object) [
                'shop_id' => self::SHOP_ID,
                'secret_key' => self::SECRET,
                'test' => true,
            ],
        ]]);
        $view = (new Wspay($firstOrder))->resolveFormView($paymentMethod);
        $formData = $view->getData()['data'];
        $firstOrder = $firstOrder->fresh();
        $secondOrder = $this->startAttempt($this->createOrder());

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/D',
            (string) $firstOrder->payment_attempt_reference
        );
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/D',
            (string) $secondOrder->payment_attempt_reference
        );
        $this->assertNotSame(
            $firstOrder->payment_attempt_reference,
            $secondOrder->payment_attempt_reference
        );
        $this->assertSame($firstOrder->payment_attempt_reference, $formData['order_id']);
        $this->assertSame('30,00', $formData['total']);
        $this->assertSame(hash('sha512', self::SHOP_ID .
            self::SECRET .
            $firstOrder->payment_attempt_reference .
            self::SECRET .
            '3000' .
            self::SECRET), $formData['md5']);
    }

    public function test_changed_random_shopping_cart_id_cannot_redirect_a_callback(): void
    {
        $this->runTransactionMigration();
        $order = $this->startAttempt($this->createOrder(true), true);
        $payload = $this->successfulCallbackPayload(
            $order,
            'wspay-tampered-reference',
            'APPROVED-TAMPERED-REFERENCE'
        );
        $payload['ShoppingCartID'] = str_repeat('f', 64) === $order->payment_attempt_reference
            ? str_repeat('e', 64)
            : str_repeat('f', 64);

        // ShoppingCartID is absent from the callback hash, so the unchanged hash
        // deliberately proves that lookup requires the exact random attempt token.
        $result = (new Wspay(new Order()))->handleCallback(new Request($payload));

        $this->assertFalse($result['success']);
        $this->assertSame(422, $result['http_status']);
        $this->assertSame(config('settings.order.status.unfinished'), (int) $order->fresh()->order_status_id);
        $this->assertNull($order->fresh()->inventory_committed_at);
        $this->assertSame(0, DB::table('order_transactions')->count());
        $this->assertSame(0, DB::table('payment_provider_references')->count());
    }

    public function test_sub_euro_form_signature_preserves_the_leading_zero(): void
    {
        $order = $this->createOrder();
        $order->update(['total' => '0.5000']);
        $paymentMethod = collect([(object) [
            'data' => (object) [
                'shop_id' => self::SHOP_ID,
                'secret_key' => self::SECRET,
                'test' => true,
            ],
        ]]);

        $view = (new Wspay($order->fresh()))->resolveFormView($paymentMethod);
        $formData = $view->getData()['data'];
        $reference = (string) $order->fresh()->payment_attempt_reference;

        $this->assertSame('0,50', $formData['total']);
        $this->assertSame(hash('sha512', self::SHOP_ID .
            self::SECRET .
            $reference .
            self::SECRET .
            '050' .
            self::SECRET), $formData['md5']);
    }

    public function test_migration_preserves_legacy_duplicates_and_keys_one_canonical_row(): void
    {
        $order = $this->createOrder();
        $other_order = $this->createOrder();
        $first_id = $this->insertTransaction($order->id, 'legacy-payment', [
            'success' => 0,
            'amount' => 12.34,
            'payment_type' => null,
            'lang' => '',
            'created_at' => '2026-09-03 10:00:00',
            'updated_at' => '2026-09-03 10:00:00',
        ]);
        $this->insertTransaction($order->id, 'legacy-payment', [
            'success' => 1,
            'amount' => 1234.00,
            'payment_type' => 'VISA',
            'lang' => 'HR',
            'created_at' => '2026-09-03 10:01:00',
            'updated_at' => '2026-09-03 10:01:00',
        ]);
        $this->insertTransaction($other_order->id, 'legacy-payment');
        $this->insertTransaction($order->id, '');
        $this->insertTransaction($order->id, '');

        $this->runTransactionMigration();

        $legacy_rows = DB::table('order_transactions')
                         ->where('order_id', $order->id)
                         ->where('pg_order_id', 'legacy-payment')
                         ->orderBy('id')
                         ->get();

        $this->assertCount(2, $legacy_rows);
        $this->assertSame($first_id, (int) $legacy_rows->first()->id);
        $this->assertNotNull($legacy_rows->first()->idempotency_key);
        $this->assertNull($legacy_rows->last()->idempotency_key);
        $this->assertSame(1, DB::table('order_transactions')->where('pg_order_id', 'legacy-payment')->where('order_id', $other_order->id)->count());
        $this->assertSame(2, DB::table('order_transactions')->where('order_id', $order->id)->where('pg_order_id', '')->count());

        $this->expectException(QueryException::class);
        $this->insertTransaction($order->id, 'another-payment', [
            'idempotency_key' => $legacy_rows->first()->idempotency_key,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code');
            $table->string('key');
            $table->text('value');
            $table->boolean('json')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_status_id');
            $table->decimal('total', 15, 4)->default(0);
            $table->string('payment_code')->nullable();
            $table->string('shipping_code')->nullable();
            $table->string('payment_card')->nullable();
            $table->unsignedInteger('payment_installment')->default(0);
            $table->timestamp('inventory_reserved_at')->nullable();
            $table->timestamp('inventory_committed_at')->nullable();
            $table->timestamp('inventory_released_at')->nullable();
            $table->timestamp('inventory_reservation_expires_at')->nullable();
            $table->unsignedInteger('inventory_reservation_version')->default(0);
            $table->string('inventory_allocation_error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('quantity')->default(0);
            $table->boolean('decrease')->default(true);
            $table->timestamps();
        });

        Schema::create('order_products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('product_id');
            $table->string('name')->default('Jedini primjerak');
            $table->unsignedInteger('quantity');
            $table->decimal('price', 15, 4)->default(30);
            $table->decimal('total', 15, 4)->default(30);
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('reservation_version');
            $table->string('action', 16);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('stock_before')->nullable();
            $table->unsignedInteger('stock_after')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['order_id', 'product_id', 'reservation_version', 'action'],
                'inventory_movement_once'
            );
        });

        Schema::create('order_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->boolean('success');
            $table->decimal('amount', 10, 2);
            $table->string('signature');
            $table->string('payment_type', 16)->nullable();
            $table->string('payment_plan', 4)->nullable();
            $table->string('payment_partner')->nullable();
            $table->dateTime('datetime');
            $table->string('approval_code')->nullable();
            $table->string('pg_order_id')->nullable();
            $table->string('lang');
            $table->string('stan')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
        });

        Schema::create('order_history', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('user_id')->default(0);
            $table->unsignedInteger('status')->default(0);
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    private function storeWspaySettings(
        string $shopId = self::SHOP_ID,
        string $secret = self::SECRET,
        bool $test = true
    ): void
    {
        DB::table('settings')->updateOrInsert([
            'code' => 'payment',
            'key' => 'list.wspay',
        ], [
            'value' => json_encode([[
                'title' => 'WSPay test',
                'code' => 'wspay',
                'data' => [
                    'shop_id' => $shopId,
                    'secret_key' => $secret,
                    'test' => $test,
                ],
                'status' => true,
                'sort_order' => 1,
            ]]),
            'json' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(bool $with_product = false, int $stock = 1): Order
    {
        $id = DB::table('orders')->insertGetId([
            'order_status_id' => config('settings.order.status.unfinished'),
            'total' => 30,
            'payment_code' => 'wspay',
            'shipping_code' => 'flat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($with_product) {
            $product_id = DB::table('products')->insertGetId([
                'name' => 'Jedini primjerak',
                'quantity' => $stock,
                'decrease' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('order_products')->insert([
                'order_id' => $id,
                'product_id' => $product_id,
                'quantity' => 1,
                'price' => 30,
                'total' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return Order::query()->findOrFail($id);
    }

    private function startAttempt(Order $order, bool $reserve = false): Order
    {
        if ($reserve) {
            $order = app(OrderInventoryService::class)->reserve(
                $order,
                now()->addMinutes(30),
                'wspay_test_checkout'
            );
        }

        return app(PaymentAttemptService::class)->start(
            $order,
            'wspay',
            null,
            'EUR',
            'test',
            self::SHOP_ID,
            self::SECRET
        );
    }

    private function insertTransaction(int $order_id, ?string $provider_order_id, array $overrides = []): int
    {
        return (int) DB::table('order_transactions')->insertGetId(array_merge([
            'order_id' => $order_id,
            'success' => 1,
            'amount' => 12.34,
            'signature' => 'signature',
            'payment_type' => null,
            'payment_plan' => null,
            'payment_partner' => null,
            'datetime' => '2026-09-03 10:00:00',
            'approval_code' => null,
            'pg_order_id' => $provider_order_id,
            'lang' => '',
            'stan' => null,
            'error' => null,
            'created_at' => '2026-09-03 10:00:00',
            'updated_at' => '2026-09-03 10:00:00',
        ], $overrides));
    }

    private function runTransactionMigration(): void
    {
        require_once database_path(
            'migrations/2026_09_03_130000_add_wspay_transaction_idempotency_key.php'
        );

        (new \AddWspayTransactionIdempotencyKey())->up();
    }

    private function runPaymentAttemptMigration(): void
    {
        require_once database_path(
            'migrations/2026_09_03_140000_add_payment_attempt_snapshot_to_orders.php'
        );

        (new \AddPaymentAttemptSnapshotToOrders())->up();
    }

    private function runProviderReferenceMigration(): void
    {
        require_once database_path(
            'migrations/2026_09_03_150000_create_payment_provider_references_table.php'
        );

        (new \CreatePaymentProviderReferencesTable())->up();
    }

    private function browserPayload(
        Order $order,
        string $providerOrderId,
        string $approvalCode,
        string $amount = '30,00'
    ): array {
        $payload = [
            'ShoppingCartID' => $order->payment_attempt_reference,
            'Success' => '1',
            'ApprovalCode' => $approvalCode,
            'WsPayOrderId' => $providerOrderId,
            'Amount' => $amount,
        ];
        $payload['Signature'] = $this->browserSignature($payload);

        return $payload;
    }

    private function successfulCallbackPayload(
        Order $order,
        string $providerOrderId,
        string $approvalCode,
        string $amount = '30,00'
    ): array {
        $payload = [
            'ShoppingCartID' => $order->payment_attempt_reference,
            'ShopID' => self::SHOP_ID,
            'ActionSuccess' => '1',
            'Authorized' => '1',
            'Completed' => '1',
            'ApprovalCode' => $approvalCode,
            'WsPayOrderId' => $providerOrderId,
            'Amount' => $amount,
            'CurrencyCode' => '978',
            'CreditCardName' => 'VISA',
            'PaymentPlan' => '0000',
        ];
        $payload['Signature'] = $this->callbackSignature($payload);

        return $payload;
    }

    private function terminalCallbackPayload(
        Order $order,
        string $providerOrderId,
        string $approvalCode,
        string $flag
    ): array {
        $payload = $this->successfulCallbackPayload($order, $providerOrderId, $approvalCode);
        $payload[$flag] = '1';
        $payload['Signature'] = $this->callbackSignature($payload);

        return $payload;
    }

    private function insertCanonicalPayment(
        Order $order,
        string $providerOrderId,
        string $approvalCode
    ): void {
        $this->insertTransaction($order->id, $providerOrderId, [
            'success' => 1,
            'amount' => 30.00,
            'approval_code' => $approvalCode,
            'provider_event' => 'payment',
            'idempotency_key' => $this->providerKey($providerOrderId),
        ]);
    }

    private function providerKey(string $providerOrderId): string
    {
        return hash('sha256', implode('|', ['wspay', 'provider', $providerOrderId]));
    }

    private function browserSignature(array $payload): string
    {
        return hash('sha512', self::SHOP_ID .
            self::SECRET .
            $payload['ShoppingCartID'] .
            self::SECRET .
            $payload['Success'] .
            self::SECRET .
            $payload['ApprovalCode'] .
            self::SECRET
        );
    }

    private function callbackSignature(array $payload): string
    {
        return hash('sha512', self::SHOP_ID .
            self::SECRET .
            $payload['ActionSuccess'] .
            $payload['ApprovalCode'] .
            self::SECRET .
            self::SHOP_ID .
            $payload['ApprovalCode'] .
            ($payload['WsPayOrderId'] ?? '')
        );
    }
}
