<?php

namespace Tests\Feature;

use App\Mail\OrderReceived;
use App\Mail\OrderSent;
use App\Models\Back\Orders\Order;
use App\Services\Orders\OrderConfirmationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderMailDeliveryTest extends TestCase
{
    /** @var string */
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'order_mail_testing',
            'database.connections.order_mail_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
            'mail.admin' => 'admin@example.test',
        ]);
        DB::purge('order_mail_testing');
        Cache::clear();

        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_status_id');
            $table->string('payment_email');
            $table->timestamp('inventory_committed_at')->nullable();
            $table->timestamp('inventory_released_at')->nullable();
            $table->string('inventory_allocation_error')->nullable();
            $table->string('payment_review_error')->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamps();
        });

        require_once database_path('migrations/2026_09_04_090000_create_order_mail_deliveries_table.php');
        (new \CreateOrderMailDeliveriesTable())->up();
    }

    protected function tearDown(): void
    {
        DB::purge('order_mail_testing');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    public function test_confirmation_deliveries_are_durable_and_each_recipient_is_sent_once(): void
    {
        Mail::fake();
        $order = $this->eligibleOrder();
        $service = new OrderConfirmationService();

        $service->enqueue($order);

        $this->assertSame(2, DB::table('order_mail_deliveries')->count());
        $this->assertTrue($service->sendOnce($order->id));
        $this->assertFalse($service->sendOnce($order->id));
        $this->assertSame(2, DB::table('order_mail_deliveries')->whereNotNull('sent_at')->count());
        $this->assertNotNull($order->fresh()->confirmation_sent_at);

        Mail::assertSent(OrderSent::class, 1);
        Mail::assertSent(OrderReceived::class, 1);
    }

    public function test_unpaid_or_inventory_failed_order_is_never_enqueued(): void
    {
        $order = $this->eligibleOrder([
            'inventory_committed_at' => null,
            'inventory_allocation_error' => 'Nema zalihe',
        ]);

        (new OrderConfirmationService())->enqueue($order);

        $this->assertSame(0, DB::table('order_mail_deliveries')->count());
    }

    private function eligibleOrder(array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'order_status_id' => 1,
            'payment_email' => 'kupac@example.test',
            'inventory_committed_at' => now(),
            'inventory_released_at' => null,
            'inventory_allocation_error' => null,
            'payment_review_error' => null,
            'confirmation_sent_at' => null,
        ], $overrides));
    }
}
