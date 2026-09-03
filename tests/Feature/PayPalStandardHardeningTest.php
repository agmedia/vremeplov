<?php

namespace Tests\Feature;

use App\Models\Back\Orders\Order;
use App\Models\Front\Checkout\Payment\PayPalStandard;
use App\Services\Inventory\OrderInventoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayPalStandardHardeningTest extends TestCase
{
    private const TEST_BUSINESS = 'tomislav-facilitator@agmedia.hr';

    /** @var string */
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'paypal_testing',
            'database.connections.paypal_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
        ]);

        DB::purge('paypal_testing');
        $this->createSchema();
        $this->storePayPalSettings(true, true);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        DB::purge('paypal_testing');
        config(['database.default' => $this->originalConnection]);

        parent::tearDown();
    }

    public function test_form_freezes_a_random_attempt_and_uses_separate_return_and_ipn_routes(): void
    {
        $order = $this->createOrder();
        [$order, $data] = $this->beginAttempt($order);

        $this->assertSame('https://www.sandbox.paypal.com/cgi-bin/webscr', $data['action']);
        $this->assertSame(route('checkout.return.paypal'), $data['return']);
        $this->assertSame(route('checkout.notify.paypal'), $data['notify_url']);
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('checkout.return.paypal')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('checkout.notify.paypal')->methods());
        $this->assertSame(1, $data['rm']);
        $this->assertSame(self::TEST_BUSINESS, $data['business']);
        $this->assertSame('HR', $data['country']);
        $this->assertCount(1, $data['products']);
        $this->assertSame('30.00', $data['products'][0]['price']);
        $this->assertSame(1, $data['products'][0]['quantity']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $data['order_id']);
        $this->assertNotSame((string) $order->id, $data['order_id']);
        $this->assertSame($data['order_id'], $order->payment_attempt_reference);
        $this->assertSame(3000, (int) $order->payment_expected_amount_minor);
        $this->assertSame('EUR', $order->payment_expected_currency);
        $this->assertSame('test', $order->payment_attempt_environment);
        $this->assertSame(self::TEST_BUSINESS, $order->payment_attempt_merchant);
        $this->assertNotNull($order->payment_attempt_started_at);

        $statusBefore = (int) $order->order_status_id;
        $this->withSession(['checkout.order' => ['id' => $order->id]])
            ->get(route('checkout.return.paypal'))
            ->assertOk()
            ->assertViewIs('front.checkout.payment_pending');

        $this->assertSame($statusBefore, (int) $order->fresh()->order_status_id);
        $this->assertSame(0, DB::table('order_transactions')->count());
    }

    public function test_completed_ipn_replays_the_exact_raw_body_and_commits_inventory_once(): void
    {
        Http::fake([
            'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' => Http::response('VERIFIED', 200),
        ]);
        $order = $this->reserveOrder($this->createOrder());
        [$order] = $this->beginAttempt($order);
        $payload = $this->validPayload($order, 'PAYPAL-TXN-INVENTORY');
        $payload['memo'] = 'razmak mora ostati isti';
        $rawBody = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);

        $this->postRawIpn($payload, $rawBody)->assertOk()->assertSeeText('OK');
        $this->postRawIpn($payload, $rawBody)->assertOk()->assertSeeText('OK');

        $freshOrder = $order->fresh();
        $this->assertSame((int) config('settings.order.status.paid'), (int) $freshOrder->order_status_id);
        $this->assertNotNull($freshOrder->inventory_committed_at);
        $this->assertNull($freshOrder->inventory_released_at);
        $this->assertSame(0, $this->stockForOrder($order));
        $this->assertSame(1, DB::table('inventory_movements')->where('action', 'reserve')->count());
        $this->assertSame(0, DB::table('inventory_movements')->where('action', 'release')->count());
        $this->assertSame(1, DB::table('order_transactions')->count());
        $this->assertSame(1, DB::table('payment_provider_references')->count());

        $transaction = DB::table('order_transactions')->first();
        $this->assertSame('PAYPAL-TXN-INVENTORY', $transaction->pg_order_id);
        $this->assertSame('paypal_completed', $transaction->provider_event);
        $this->assertSame(1, (int) $transaction->success);
        $this->assertEquals(30.00, (float) $transaction->amount);
        $this->assertNotNull($transaction->idempotency_key);

        Http::assertSentCount(2);
        Http::assertSent(function (ClientRequest $request) use ($rawBody) {
            return $request->url() === 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr'
                && $request->body() === 'cmd=_notify-validate&' . $rawBody;
        });
    }

    public function test_invalid_local_fields_are_rejected_before_contacting_paypal(): void
    {
        Http::fake();

        $invalidMutations = [
            ['custom' => str_repeat('a', 64)],
            ['receiver_email' => 'attacker@example.test'],
            ['mc_currency' => 'USD'],
            ['mc_gross' => '29.99'],
            ['payment_status' => 'Unknown'],
            ['txn_id' => 'bad transaction id'],
        ];

        foreach ($invalidMutations as $index => $mutation) {
            [$order] = $this->beginAttempt($this->createOrder());
            $payload = array_merge(
                $this->validPayload($order, 'PAYPAL-TXN-BAD-' . $index),
                $mutation
            );
            $result = (new PayPalStandard($order))->handleNotification(
                $order,
                $this->ipnRequest($payload)
            );

            $this->assertFalse($result['accepted'], 'Invalid PayPal field set #' . $index . ' was accepted.');
            $this->assertSame(400, $result['http_status']);
            $this->assertSame(
                (int) config('settings.order.status.unfinished'),
                (int) $order->fresh()->order_status_id
            );
        }

        $this->assertSame(0, DB::table('order_transactions')->count());
        $this->assertSame(0, DB::table('payment_provider_references')->count());
        Http::assertNothingSent();
    }

    public function test_unverified_notification_cannot_change_order_or_create_transaction(): void
    {
        Http::fake([
            'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' => Http::response('INVALID', 200),
        ]);
        [$order] = $this->beginAttempt($this->createOrder());
        $payload = $this->validPayload($order, 'PAYPAL-TXN-INVALID');

        $result = (new PayPalStandard($order))->handleNotification(
            $order,
            $this->ipnRequest($payload)
        );

        $this->assertFalse($result['accepted']);
        $this->assertSame(400, $result['http_status']);
        $this->assertSame(
            (int) config('settings.order.status.unfinished'),
            (int) $order->fresh()->order_status_id
        );
        $this->assertSame(0, DB::table('order_transactions')->count());
        $this->assertSame(0, DB::table('payment_provider_references')->count());
    }

    public function test_provider_transaction_reference_cannot_move_between_orders_even_with_a_different_status(): void
    {
        Http::fake([
            'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' => Http::response('VERIFIED', 200),
        ]);
        [$first] = $this->beginAttempt($this->reserveOrder($this->createOrder()));
        [$second] = $this->beginAttempt($this->reserveOrder($this->createOrder()));
        $txnId = 'PAYPAL-GLOBAL-REFERENCE';

        $pending = $this->validPayload($first, $txnId, [
            'payment_status' => 'Pending',
            'pending_reason' => 'paymentreview',
        ]);
        $firstResult = (new PayPalStandard($first))->handleNotification(
            $first,
            $this->ipnRequest($pending)
        );

        $completedForAnotherOrder = $this->validPayload($second, $txnId);
        $secondResult = (new PayPalStandard($second))->handleNotification(
            $second,
            $this->ipnRequest($completedForAnotherOrder)
        );

        $this->assertTrue($firstResult['accepted']);
        $this->assertFalse($secondResult['accepted']);
        $this->assertSame(409, $secondResult['http_status']);
        $this->assertSame($first->id, (int) DB::table('payment_provider_references')->value('order_id'));
        $this->assertSame(1, DB::table('payment_provider_references')->count());
        $this->assertSame(1, DB::table('order_transactions')->count());
        $this->assertSame(0, DB::table('order_transactions')->where('order_id', $second->id)->count());
        $this->assertSame(
            (int) config('settings.order.status.unfinished'),
            (int) $second->fresh()->order_status_id
        );
        $this->assertNull($second->fresh()->inventory_committed_at);
    }

    public function test_pending_then_completed_for_the_same_order_records_both_events_and_commits_once(): void
    {
        Http::fake([
            'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' => Http::response('VERIFIED', 200),
        ]);
        [$order] = $this->beginAttempt($this->reserveOrder($this->createOrder()));
        $txnId = 'PAYPAL-PENDING-COMPLETED';
        $pending = $this->validPayload($order, $txnId, [
            'payment_status' => 'Pending',
            'pending_reason' => 'paymentreview',
        ]);

        $pendingResult = (new PayPalStandard($order))->handleNotification(
            $order,
            $this->ipnRequest($pending)
        );

        $this->assertTrue($pendingResult['accepted']);
        $this->assertSame(
            (int) config('settings.order.status.unfinished'),
            (int) $order->fresh()->order_status_id
        );
        $this->assertNull($order->fresh()->inventory_committed_at);

        $completedResult = (new PayPalStandard($order->fresh()))->handleNotification(
            $order->fresh(),
            $this->ipnRequest($this->validPayload($order->fresh(), $txnId))
        );

        $this->assertTrue($completedResult['accepted']);
        $this->assertTrue($completedResult['should_notify']);
        $this->assertSame(
            (int) config('settings.order.status.paid'),
            (int) $order->fresh()->order_status_id
        );
        $this->assertNotNull($order->fresh()->inventory_committed_at);
        $this->assertSame(0, $this->stockForOrder($order));
        $this->assertSame(1, DB::table('inventory_movements')->where('action', 'reserve')->count());
        $this->assertSame(1, DB::table('payment_provider_references')->count());
        $this->assertSame(
            ['paypal_pending', 'paypal_completed'],
            DB::table('order_transactions')->orderBy('id')->pluck('provider_event')->all()
        );
        $this->assertSame([0, 1], DB::table('order_transactions')->orderBy('id')->pluck('success')->map(function ($value) {
            return (int) $value;
        })->all());
    }

    public function test_refund_and_reversal_events_never_restore_physical_stock_automatically(): void
    {
        Http::fake([
            'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' => Http::response('VERIFIED', 200),
        ]);

        [$refundedOrder] = $this->beginAttempt($this->reserveOrder($this->createOrder()));
        $paymentTxn = 'PAYPAL-PAID-FOR-REFUND';
        $this->handle($refundedOrder, $this->validPayload($refundedOrder, $paymentTxn));
        $refund = $this->validPayload($refundedOrder->fresh(), 'PAYPAL-FULL-REFUND', [
            'payment_status' => 'Refunded',
            'mc_gross' => '-30.00',
            'parent_txn_id' => $paymentTxn,
        ]);
        $refundResult = $this->handle($refundedOrder->fresh(), $refund);

        $this->assertTrue($refundResult['accepted']);
        $this->assertSame(
            (int) config('settings.order.status.refund'),
            (int) $refundedOrder->fresh()->order_status_id
        );
        $this->assertSame(0, $this->stockForOrder($refundedOrder));
        $this->assertNotNull($refundedOrder->fresh()->inventory_committed_at);
        $this->assertNull($refundedOrder->fresh()->inventory_released_at);
        $this->assertSame(
            0,
            DB::table('inventory_movements')->where('order_id', $refundedOrder->id)->where('action', 'release')->count()
        );

        [$reviewOrder] = $this->beginAttempt($this->reserveOrder($this->createOrder()));
        $reviewPaymentTxn = 'PAYPAL-PAID-FOR-REVERSAL';
        $this->handle($reviewOrder, $this->validPayload($reviewOrder, $reviewPaymentTxn));
        $partialReversal = $this->validPayload($reviewOrder->fresh(), 'PAYPAL-PARTIAL-REVERSAL', [
            'payment_status' => 'Reversed',
            'mc_gross' => '-10.00',
            'parent_txn_id' => $reviewPaymentTxn,
        ]);
        $reversalResult = $this->handle($reviewOrder->fresh(), $partialReversal);

        $this->assertTrue($reversalResult['accepted']);
        $this->assertSame(
            (int) config('settings.order.status.paid'),
            (int) $reviewOrder->fresh()->order_status_id
        );
        $this->assertNotNull($reviewOrder->fresh()->payment_review_error);
        $this->assertStringContainsString('manual review', $reviewOrder->fresh()->payment_review_error);
        $this->assertSame(0, $this->stockForOrder($reviewOrder));
        $this->assertNull($reviewOrder->fresh()->inventory_released_at);
        $this->assertSame(
            0,
            DB::table('inventory_movements')->where('order_id', $reviewOrder->id)->where('action', 'release')->count()
        );
    }

    public function test_verified_payment_without_stock_stays_unresolved_on_replay(): void
    {
        Http::fake([
            'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' => Http::response('VERIFIED', 200),
        ]);
        [$order] = $this->beginAttempt($this->createOrder());
        DB::table('products')->where('id', $this->productIdForOrder($order))->update(['quantity' => 0]);
        $payload = $this->validPayload($order, 'PAYPAL-TXN-NO-STOCK');

        $first = $this->handle($order, $payload);
        $afterFirst = $order->fresh();
        $second = $this->handle($afterFirst, $payload);
        $afterReplay = $order->fresh();

        $this->assertTrue($first['accepted']);
        $this->assertFalse($first['should_notify']);
        $this->assertTrue($second['accepted']);
        $this->assertFalse($second['should_notify']);
        $this->assertSame(
            (int) config('settings.order.status.call_when_found'),
            (int) $afterReplay->order_status_id
        );
        $this->assertSame($afterFirst->inventory_allocation_error, $afterReplay->inventory_allocation_error);
        $this->assertNotNull($afterReplay->inventory_allocation_error);
        $this->assertNull($afterReplay->inventory_committed_at);
        $this->assertSame(1, DB::table('order_transactions')->count());
        $this->assertSame(1, DB::table('payment_provider_references')->count());
        $this->assertSame(0, DB::table('inventory_movements')->count());
        $this->assertSame(0, $this->stockForOrder($order));
    }

    public function test_frozen_attempt_survives_provider_deactivation_and_configuration_change(): void
    {
        Http::fake([
            'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' => Http::response('VERIFIED', 200),
            'https://ipnpb.paypal.com/cgi-bin/webscr' => Http::response('INVALID', 200),
        ]);
        [$order] = $this->beginAttempt($this->reserveOrder($this->createOrder()));
        $frozenReference = $order->payment_attempt_reference;

        $this->storePayPalSettings(false, false);

        $payload = $this->validPayload($order, 'PAYPAL-FROZEN-CONFIG');
        $this->postRawIpn($payload)->assertOk()->assertSeeText('OK');

        $fresh = $order->fresh();
        $this->assertSame($frozenReference, $fresh->payment_attempt_reference);
        $this->assertSame('test', $fresh->payment_attempt_environment);
        $this->assertSame(self::TEST_BUSINESS, $fresh->payment_attempt_merchant);
        $this->assertSame((int) config('settings.order.status.paid'), (int) $fresh->order_status_id);
        $this->assertNotNull($fresh->inventory_committed_at);
        Http::assertSentCount(1);
        Http::assertSent(function (ClientRequest $request) {
            return $request->url() === 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
        });
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
            $table->string('payment_fname')->default('Ivo');
            $table->string('payment_lname')->default('Ivic');
            $table->string('payment_address')->default('Test 1');
            $table->string('payment_state')->default('Croatia');
            $table->string('payment_zip')->default('10000');
            $table->string('payment_city')->default('Zagreb');
            $table->string('payment_phone')->nullable();
            $table->string('payment_email')->default('buyer@example.test');
            $table->timestamp('inventory_reserved_at')->nullable();
            $table->timestamp('inventory_committed_at')->nullable();
            $table->timestamp('inventory_released_at')->nullable();
            $table->timestamp('inventory_reservation_expires_at')->nullable();
            $table->unsignedInteger('inventory_reservation_version')->default(0);
            $table->string('inventory_allocation_error', 500)->nullable();
            $table->timestamp('payment_attempt_started_at')->nullable();
            $table->string('payment_attempt_provider', 32)->nullable();
            $table->string('payment_attempt_reference', 191)->nullable();
            $table->unsignedBigInteger('payment_expected_amount_minor')->nullable();
            $table->char('payment_expected_currency', 3)->nullable();
            $table->string('payment_attempt_environment', 16)->nullable();
            $table->string('payment_attempt_merchant', 191)->nullable();
            $table->text('payment_attempt_verification_key')->nullable();
            $table->char('payment_attempt_order_hash', 64)->nullable();
            $table->unsignedInteger('payment_attempt_reservation_version')->nullable();
            $table->string('payment_review_error', 500)->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['payment_attempt_provider', 'payment_attempt_reference'],
                'paypal_attempt_reference_unique'
            );
        });

        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->default('PayPal test book');
            $table->string('sku')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('decrease')->default(true);
            $table->timestamps();
        });

        Schema::create('order_products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('product_id');
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->decimal('price', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
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
                'paypal_inventory_movement_once'
            );
        });

        Schema::create('order_total', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->string('code')->nullable();
            $table->decimal('value', 15, 4)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
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
            $table->string('provider_event', 32)->nullable();
            $table->dateTime('datetime');
            $table->string('approval_code')->nullable();
            $table->string('pg_order_id')->nullable();
            $table->char('idempotency_key', 64)->nullable();
            $table->string('lang');
            $table->string('stan')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->unique('idempotency_key', 'paypal_transaction_idempotency_unique');
        });

        Schema::create('payment_provider_references', function (Blueprint $table) {
            $table->increments('id');
            $table->string('provider', 32);
            $table->string('reference', 191);
            $table->unsignedInteger('order_id');
            $table->timestamps();

            $table->unique(['provider', 'reference'], 'paypal_provider_reference_unique');
            $table->index('order_id');
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

    private function storePayPalSettings(bool $testMode, bool $active): void
    {
        DB::table('settings')->updateOrInsert(
            [
                'code' => 'payment',
                'key' => 'list.paypal',
            ],
            [
                'value' => json_encode([[
                    'title' => 'PayPal test',
                    'code' => 'paypal',
                    'data' => [
                        'test' => $testMode,
                    ],
                    'status' => $active,
                ]]),
                'json' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function createOrder(string $paymentCode = 'paypal', int $stock = 1): Order
    {
        $id = DB::table('orders')->insertGetId([
            'order_status_id' => config('settings.order.status.unfinished'),
            'total' => 30,
            'payment_code' => $paymentCode,
            'shipping_code' => 'gls',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'sku' => 'PAYPAL-BOOK-' . $id,
            'quantity' => $stock,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_products')->insert([
            'order_id' => $id,
            'product_id' => $productId,
            'name' => 'PayPal test book',
            'quantity' => 1,
            'price' => 30,
            'total' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_total')->insert([
            'order_id' => $id,
            'code' => 'subtotal',
            'value' => 30,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::query()->findOrFail($id);
    }

    private function reserveOrder(Order $order): Order
    {
        return app(OrderInventoryService::class)->reserve(
            $order,
            now()->addMinutes(30),
            'paypal_test_checkout'
        );
    }

    private function beginAttempt(Order $order): array
    {
        $method = (object) [
            'data' => (object) ['test' => true],
        ];
        $view = (new PayPalStandard($order))->resolveFormView(collect([$method]));

        return [$order->fresh(), $view->getData()['data']];
    }

    private function validPayload(Order $order, string $txnId, array $overrides = []): array
    {
        return array_merge([
            'payment_status' => 'Completed',
            'custom' => (string) ($order->payment_attempt_reference ?: $order->id),
            'receiver_email' => self::TEST_BUSINESS,
            'business' => self::TEST_BUSINESS,
            'mc_currency' => 'EUR',
            'mc_gross' => '30.00',
            'txn_id' => $txnId,
            'payment_type' => 'instant',
            'payment_date' => '03:12:24 Sep 03, 2026 PDT',
            'verify_sign' => 'verified-by-paypal',
            'invoice' => $order->id . ' - Test Buyer',
            'notify_version' => '3.9',
        ], $overrides);
    }

    private function ipnRequest(array $payload, ?string $rawBody = null): Request
    {
        $rawBody = $rawBody ?? http_build_query($payload, '', '&', PHP_QUERY_RFC3986);

        return Request::create(
            '/paypal/ipn',
            'POST',
            $payload,
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            $rawBody
        );
    }

    private function postRawIpn(array $payload, ?string $rawBody = null)
    {
        $rawBody = $rawBody ?? http_build_query($payload, '', '&', PHP_QUERY_RFC3986);

        return $this->call(
            'POST',
            route('checkout.notify.paypal'),
            $payload,
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            $rawBody
        );
    }

    private function handle(Order $order, array $payload): array
    {
        return (new PayPalStandard($order))->handleNotification(
            $order,
            $this->ipnRequest($payload)
        );
    }

    private function productIdForOrder(Order $order): int
    {
        return (int) DB::table('order_products')
            ->where('order_id', $order->id)
            ->value('product_id');
    }

    private function stockForOrder(Order $order): int
    {
        return (int) DB::table('products')
            ->where('id', $this->productIdForOrder($order))
            ->value('quantity');
    }
}
