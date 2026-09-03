<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocalCheckoutConfirmationTest extends TestCase
{
    /** @var string */
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'local_checkout_testing',
            'database.connections.local_checkout_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge('local_checkout_testing');
        $this->createSchema();

        foreach (['cod', 'bank', 'pickup'] as $paymentCode) {
            $this->storePaymentSetting($paymentCode);
        }
    }

    protected function tearDown(): void
    {
        DB::purge('local_checkout_testing');
        config(['database.default' => $this->originalConnection]);

        parent::tearDown();
    }

    public function test_local_confirmation_route_is_post_only_and_uses_web_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('checkout.local');

        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());
        $this->assertContains('web', $route->gatherMiddleware());
    }

    public function test_legacy_get_confirmation_is_rejected_without_mutating_order(): void
    {
        $orderId = $this->createOrder('cod', 8);

        $response = $this->withSession($this->checkoutSession($orderId))
            ->get(route('checkout', ['provjera' => $orderId]));

        $response->assertStatus(405);
        $this->assertSame(8, $this->orderStatus($orderId));
        $this->assertNull(DB::table('orders')->where('id', $orderId)->value('inventory_committed_at'));
    }

    public function test_post_cannot_confirm_another_sequential_order_id(): void
    {
        $ownOrderId = $this->createOrder('cod', 8);
        $otherOrderId = $this->createOrder('cod', 8);

        $response = $this->withSession($this->checkoutSession($ownOrderId))
            ->post(route('checkout.local'), ['provjera' => $otherOrderId]);

        $response->assertForbidden();
        $this->assertSame(8, $this->orderStatus($ownOrderId));
        $this->assertSame(8, $this->orderStatus($otherOrderId));
    }

    public function test_external_identifier_cannot_be_used_to_finish_a_local_order(): void
    {
        $orderId = $this->createOrder('pickup', 8);

        $response = $this->get(route('checkout', ['order_number' => $orderId]));

        $response->assertForbidden();
        $this->assertSame(8, $this->orderStatus($orderId));
    }

    public function test_fake_wspay_identifier_cannot_dispatch_to_local_provider(): void
    {
        $orderId = $this->createOrder('cod', 8);

        $response = $this->get(route('checkout', [
            'ShoppingCartID' => $orderId . '-2026',
        ]));

        $response->assertStatus(422);
        $this->assertSame(8, $this->orderStatus($orderId));
    }

    public function test_multiple_order_identifiers_are_rejected_without_mutation(): void
    {
        $orderId = $this->createOrder('cod', 8);

        $response = $this->withSession($this->checkoutSession($orderId))->post(
            route('checkout.local'),
            [
                'provjera' => $orderId,
                'ShoppingCartID' => $orderId . '-2026',
            ]
        );

        $response->assertStatus(422);
        $this->assertSame(8, $this->orderStatus($orderId));
    }

    public function test_post_rejects_non_unfinished_or_non_local_session_order(): void
    {
        $alreadyConfirmedId = $this->createOrder('bank', 1);
        $externalPaymentId = $this->createOrder('wspay', 8);

        $this->withSession($this->checkoutSession($alreadyConfirmedId))
            ->post(route('checkout.local'), ['provjera' => $alreadyConfirmedId])
            ->assertForbidden();

        $this->withSession($this->checkoutSession($externalPaymentId))
            ->post(route('checkout.local'), ['provjera' => $externalPaymentId])
            ->assertForbidden();

        $this->assertSame(1, $this->orderStatus($alreadyConfirmedId));
        $this->assertSame(8, $this->orderStatus($externalPaymentId));
    }

    public function test_each_local_payment_can_confirm_current_unfinished_order_once(): void
    {
        foreach (['cod', 'bank', 'pickup'] as $paymentCode) {
            [$orderId, $productId] = $this->createStockedOrder($paymentCode);

            $response = $this->withSession($this->checkoutSession($orderId))
                ->post(route('checkout.local'), ['provjera' => $orderId]);

            $response->assertRedirect(route('checkout.success'));
            $this->assertSame(1, $this->orderStatus($orderId));
            $this->assertNotNull(
                DB::table('orders')->where('id', $orderId)->value('inventory_committed_at')
            );
            $this->assertSame(0, (int) DB::table('products')->where('id', $productId)->value('quantity'));

            $this->withSession($this->checkoutSession($orderId))
                ->post(route('checkout.local'), ['provjera' => $orderId])
                ->assertForbidden();
            $this->assertSame(0, (int) DB::table('products')->where('id', $productId)->value('quantity'));
            $this->assertSame(
                1,
                DB::table('inventory_movements')
                    ->where('order_id', $orderId)
                    ->where('action', 'reserve')
                    ->count()
            );
        }
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
            $table->string('payment_code')->nullable();
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
            $table->string('name')->default('Testna knjiga');
            $table->unsignedInteger('quantity');
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
                'local_checkout_inventory_once'
            );
        });
    }

    private function storePaymentSetting(string $code): void
    {
        DB::table('settings')->insert([
            'code' => 'payment',
            'key' => 'list.' . $code,
            'value' => json_encode([[
                'title' => strtoupper($code),
                'code' => $code,
                'status' => true,
                'sort_order' => 0,
                'data' => [],
            ]]),
            'json' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(string $paymentCode, int $status): int
    {
        return (int) DB::table('orders')->insertGetId([
            'order_status_id' => $status,
            'payment_code' => $paymentCode,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createStockedOrder(string $paymentCode): array
    {
        $productId = (int) DB::table('products')->insertGetId([
            'name' => 'Zadnji primjerak',
            'quantity' => 1,
            'decrease' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = $this->createOrder($paymentCode, 8);

        DB::table('order_products')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$orderId, $productId];
    }

    private function checkoutSession(int $orderId): array
    {
        return [
            'checkout' => [
                'order' => ['id' => $orderId],
            ],
        ];
    }

    private function orderStatus(int $orderId): int
    {
        return (int) DB::table('orders')->where('id', $orderId)->value('order_status_id');
    }
}
