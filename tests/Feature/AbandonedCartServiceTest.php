<?php

namespace Tests\Feature;

use App\Mail\AbandonedCartReminderMail;
use App\Models\Back\Orders\Order;
use App\Services\Orders\AbandonedCartService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AbandonedCartServiceTest extends TestCase
{
    /** @var string */
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'abandoned_testing',
            'database.connections.abandoned_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
            'abandoned_cart.enabled' => true,
            'abandoned_cart.starts_at' => now()->subDay()->toDateTimeString(),
            'abandoned_cart.delays_minutes' => [1 => 60, 2 => 1440],
        ]);
        DB::purge('abandoned_testing');
        Cache::clear();
        $this->schema();
    }

    protected function tearDown(): void
    {
        DB::purge('abandoned_testing');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    public function test_reminder_is_sent_once_and_newer_completed_purchase_suppresses_old_checkout(): void
    {
        Mail::fake();
        $suppressed = $this->order(1, 'done@example.test', 8, now()->subHours(3));
        $this->product($suppressed->id);
        $this->order(2, 'done@example.test', 1, now()->subHours(2));

        $eligible = $this->order(3, 'buy@example.test', 8, now()->subHours(2));
        $this->product($eligible->id);
        $service = new AbandonedCartService();

        $this->assertSame([3], $service->candidates(1, 20)->pluck('id')->all());
        $this->assertTrue($service->send($eligible, 1));
        $this->assertTrue($service->send($eligible, 1));

        $this->assertSame(1, DB::table('abandoned_cart_reminders')->count());
        Mail::assertSent(AbandonedCartReminderMail::class, 1);
    }

    public function test_feature_is_fail_closed_until_explicitly_enabled(): void
    {
        config(['abandoned_cart.enabled' => false]);
        $order = $this->order(4, 'disabled@example.test', 8, now()->subHours(2));
        $this->product($order->id);

        $this->assertCount(0, (new AbandonedCartService())->candidates(1, 20));
        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
    }

    private function order(int $id, string $email, int $status, $createdAt): Order
    {
        DB::table('orders')->insert([
            'id' => $id,
            'order_status_id' => $status,
            'payment_email' => $email,
            'payment_fname' => 'Kupac',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return Order::query()->findOrFail($id);
    }

    private function product(int $orderId): void
    {
        DB::table('products')->insertOrIgnore(['id' => $orderId, 'status' => 1, 'quantity' => 1]);
        DB::table('order_products')->insert([
            'order_id' => $orderId,
            'product_id' => $orderId,
            'name' => 'Testna knjiga',
            'quantity' => 1,
            'price' => 10,
            'total' => 10,
        ]);
    }

    private function schema(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_status_id');
            $table->string('payment_email')->nullable();
            $table->string('payment_fname')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->boolean('status')->default(true);
            $table->unsignedInteger('quantity')->default(1);
        });
        Schema::create('order_products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('product_id');
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->decimal('price', 15, 4);
            $table->decimal('total', 15, 4);
        });
        Schema::create('order_total', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->string('code')->nullable();
            $table->string('title')->nullable();
            $table->decimal('value', 15, 4)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
        });

        require_once database_path('migrations/2026_09_04_100000_create_abandoned_cart_reminders_table.php');
        (new \CreateAbandonedCartRemindersTable())->up();
    }
}
