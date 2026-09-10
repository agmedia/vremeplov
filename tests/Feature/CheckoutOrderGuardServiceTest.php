<?php

namespace Tests\Feature;

use App\Services\Orders\CheckoutOrderGuardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CheckoutOrderGuardServiceTest extends TestCase
{
    /** @var string */
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'checkout_guard_testing',
            'database.connections.checkout_guard_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'settings.order.status.unfinished' => 8,
        ]);

        DB::purge('checkout_guard_testing');

        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_status_id');
            $table->string('payment_email');
            $table->timestamps();
        });

        Schema::create('order_products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('quantity');
        });

        Schema::create('checkout_order_guards', function (Blueprint $table) {
            $table->char('fingerprint', 64)->primary();
            $table->unsignedInteger('order_id')->nullable()->index();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('checkout_guard_testing');
        config(['database.default' => $this->originalConnection]);

        parent::tearDown();
    }

    public function test_repeated_checkout_for_same_customer_and_items_reuses_one_unfinished_order(): void
    {
        $service = app(CheckoutOrderGuardService::class);
        $data = $this->checkoutData('Kupac@Example.test', [41 => 1, 99 => 2]);

        $firstOrderId = $this->prepareOrder($service, $data);
        $secondOrderId = $this->prepareOrder($service, $this->checkoutData(' kupac@example.test ', [99 => 2, 41 => 1]));

        $this->assertSame($firstOrderId, $secondOrderId);
        $this->assertSame(1, DB::table('orders')->count());
    }

    public function test_completed_order_does_not_block_a_later_identical_purchase(): void
    {
        $service = app(CheckoutOrderGuardService::class);
        $data = $this->checkoutData('kupac@example.test', [41 => 1]);
        $firstOrderId = $this->prepareOrder($service, $data);

        DB::table('orders')->where('id', $firstOrderId)->update(['order_status_id' => 1]);

        $secondOrderId = $this->prepareOrder($service, $data);

        $this->assertNotSame($firstOrderId, $secondOrderId);
        $this->assertSame(2, DB::table('orders')->count());
    }

    private function prepareOrder(CheckoutOrderGuardService $service, array $data): int
    {
        return DB::transaction(function () use ($service, $data) {
            $fingerprint = $service->fingerprint($data);
            $existing = $service->lockMatchingUnfinished($fingerprint);

            if ($existing) {
                return (int) $existing->id;
            }

            $orderId = (int) DB::table('orders')->insertGetId([
                'order_status_id' => 8,
                'payment_email' => trim((string) data_get($data, 'address.email')),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (data_get($data, 'cart.items', []) as $item) {
                DB::table('order_products')->insert([
                    'order_id' => $orderId,
                    'product_id' => $item->id,
                    'quantity' => $item->quantity,
                ]);
            }

            $service->remember($fingerprint, $orderId);

            return $orderId;
        });
    }

    private function checkoutData(string $email, array $items): array
    {
        return [
            'address' => ['email' => $email],
            'cart' => [
                'items' => collect($items)->map(function ($quantity, $id) {
                    return (object) ['id' => $id, 'quantity' => $quantity];
                })->values(),
            ],
        ];
    }
}
