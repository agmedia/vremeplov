<?php

namespace Tests\Feature;

use App\Mail\OrderReceived;
use App\Mail\OrderSent;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderMailDelivery;
use App\Services\Orders\OrderConfirmationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use RuntimeException;
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
            'mail.order_confirmation.max_attempts' => 12,
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
        Carbon::setTestNow();
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

    public function test_failed_delivery_stops_at_configured_limit_and_keeps_pii_safe_diagnostics(): void
    {
        Carbon::setTestNow('2026-10-06 12:00:00');
        config(['mail.order_confirmation.max_attempts' => 2]);
        Log::spy();

        $order = $this->eligibleOrder();
        $service = new OrderConfirmationService();
        $service->enqueue($order);

        DB::table('order_mail_deliveries')
            ->where('order_id', $order->id)
            ->where('type', 'admin_notification')
            ->update(['sent_at' => now(), 'next_attempt_at' => null]);

        $privateDiagnostic = 'SMTP rejected kupac@example.test from 203.0.113.7';
        Mail::shouldReceive('to')
            ->twice()
            ->with('kupac@example.test')
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->twice()
            ->andThrow(new RuntimeException($privateDiagnostic, 451));

        $this->assertFalse($service->sendOnce($order->id));

        $firstAttempt = OrderMailDelivery::query()
            ->where('order_id', $order->id)
            ->where('type', 'customer_confirmation')
            ->firstOrFail();
        $this->assertSame(1, $firstAttempt->attempts);
        $this->assertSame('RuntimeException; code=451; smtp_status=451; attempt=1/2; exhausted=no', $firstAttempt->last_error);
        $this->assertNotNull($firstAttempt->next_attempt_at);

        Carbon::setTestNow(now()->addMinutes(2));
        $this->assertSame(['sent' => 0, 'failed' => 1], $service->processPending());

        $exhausted = $firstAttempt->fresh();
        $this->assertSame(2, $exhausted->attempts);
        $this->assertSame('RuntimeException; code=451; smtp_status=451; attempt=2/2; exhausted=yes', $exhausted->last_error);
        $this->assertNull($exhausted->next_attempt_at);
        $this->assertStringNotContainsString('kupac@example.test', $exhausted->last_error);
        $this->assertStringNotContainsString('203.0.113.7', $exhausted->last_error);

        $this->assertSame(['sent' => 0, 'failed' => 0], $service->processPending());
        $this->assertFalse($service->sendOnce($order->id));
        $this->assertSame(2, $exhausted->fresh()->attempts);

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context) use ($privateDiagnostic): bool {
                return $message === 'Order mail delivery failed.'
                    && ($context['retry_exhausted'] ?? false) === true
                    && ($context['exception_type'] ?? null) === 'RuntimeException'
                    && ($context['exception_code'] ?? null) === 451
                    && ($context['smtp_status'] ?? null) === 451
                    && ! str_contains(json_encode($context), $privateDiagnostic)
                    && ! str_contains(json_encode($context), 'kupac@example.test');
            })
            ->once();
    }

    public function test_send_delivery_rechecks_limit_after_locking(): void
    {
        config(['mail.order_confirmation.max_attempts' => 2]);
        Mail::fake();

        $order = $this->eligibleOrder();
        $service = new OrderConfirmationService();
        $service->enqueue($order);

        $delivery = OrderMailDelivery::query()
            ->where('order_id', $order->id)
            ->where('type', 'customer_confirmation')
            ->firstOrFail();
        $delivery->forceFill([
            'attempts' => 2,
            'next_attempt_at' => now(),
            'last_error' => 'RuntimeException; code=451; smtp_status=451; attempt=2/2; exhausted=yes',
        ])->save();

        $method = new ReflectionMethod($service, 'sendDelivery');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($service, $delivery));
        $this->assertNull($delivery->fresh()->next_attempt_at);
        $this->assertSame(2, $delivery->fresh()->attempts);
        Mail::assertNothingSent();
    }

    public function test_transport_status_is_retained_without_storing_sensitive_exception_text(): void
    {
        config(['mail.order_confirmation.max_attempts' => 1]);
        Log::spy();

        $order = $this->eligibleOrder();
        $service = new OrderConfirmationService();
        $service->enqueue($order);
        DB::table('order_mail_deliveries')
            ->where('order_id', $order->id)
            ->where('type', 'admin_notification')
            ->update(['sent_at' => now(), 'next_attempt_at' => null]);

        $sensitiveMessage = 'Expected response code 354 but got code "503", with message "recipient kupac@example.test rejected"';
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \Swift_TransportException($sensitiveMessage));

        $this->assertFalse($service->sendOnce($order->id));

        $delivery = OrderMailDelivery::query()
            ->where('order_id', $order->id)
            ->where('type', 'customer_confirmation')
            ->firstOrFail();
        $this->assertSame(
            'Swift_TransportException; code=0; smtp_status=503; attempt=1/1; exhausted=yes',
            $delivery->last_error
        );
        $this->assertStringNotContainsString('kupac@example.test', $delivery->last_error);
        $this->assertStringNotContainsString('recipient', $delivery->last_error);

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Order mail delivery failed.'
                    && ($context['smtp_status'] ?? null) === 503
                    && ! str_contains(json_encode($context), 'kupac@example.test');
            })
            ->once();
    }

    public function test_missing_order_deliveries_become_terminal_and_are_not_processed_again(): void
    {
        config(['mail.order_confirmation.max_attempts' => 2]);
        Mail::fake();

        $order = $this->eligibleOrder();
        $service = new OrderConfirmationService();
        $service->enqueue($order);
        $orderId = (int) $order->id;
        $order->delete();

        $this->assertSame(['sent' => 0, 'failed' => 1], $service->processPending());

        $deliveries = OrderMailDelivery::query()->where('order_id', $orderId)->get();
        $this->assertCount(2, $deliveries);

        foreach ($deliveries as $delivery) {
            $this->assertSame(2, $delivery->attempts);
            $this->assertNull($delivery->next_attempt_at);
            $this->assertSame(
                'OrderUnavailable; reason=order_missing; attempt=2/2; exhausted=yes',
                $delivery->last_error
            );
        }

        $this->assertSame(['sent' => 0, 'failed' => 0], $service->processPending());
        Mail::assertNothingSent();
    }

    public function test_ineligible_order_deliveries_become_terminal(): void
    {
        config(['mail.order_confirmation.max_attempts' => 3]);
        Mail::fake();

        $order = $this->eligibleOrder();
        $service = new OrderConfirmationService();
        $service->enqueue($order);
        $order->forceFill(['inventory_released_at' => now()])->save();

        $this->assertFalse($service->sendOnce($order->id));

        $deliveries = OrderMailDelivery::query()->where('order_id', $order->id)->get();
        $this->assertCount(2, $deliveries);

        foreach ($deliveries as $delivery) {
            $this->assertSame(3, $delivery->attempts);
            $this->assertNull($delivery->next_attempt_at);
            $this->assertSame(
                'OrderUnavailable; reason=order_ineligible; attempt=3/3; exhausted=yes',
                $delivery->last_error
            );
        }

        Mail::assertNothingSent();
    }

    public function test_send_delivery_terminalizes_order_that_becomes_ineligible_after_locking(): void
    {
        config(['mail.order_confirmation.max_attempts' => 2]);
        Mail::fake();

        $order = $this->eligibleOrder();
        $service = new OrderConfirmationService();
        $service->enqueue($order);
        $delivery = OrderMailDelivery::query()
            ->where('order_id', $order->id)
            ->where('type', 'customer_confirmation')
            ->firstOrFail();

        $lock = \Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $lock->shouldReceive('get')->once()->andReturnUsing(function () use ($order): bool {
            $order->forceFill(['inventory_released_at' => now()])->save();

            return true;
        });
        $lock->shouldReceive('release')->once()->andReturn(true);
        Cache::shouldReceive('lock')
            ->once()
            ->with('order-mail-delivery:' . $delivery->id, 120)
            ->andReturn($lock);

        $method = new ReflectionMethod($service, 'sendDelivery');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($service, $delivery));

        $terminal = $delivery->fresh();
        $this->assertSame(2, $terminal->attempts);
        $this->assertNull($terminal->next_attempt_at);
        $this->assertSame(
            'OrderUnavailable; reason=order_ineligible; attempt=2/2; exhausted=yes',
            $terminal->last_error
        );
        Mail::assertNothingSent();
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
