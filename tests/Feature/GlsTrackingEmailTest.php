<?php

namespace Tests\Feature;

use App\Mail\ShippingTrackingAvailable;
use App\Models\Back\Orders\Order;
use App\Services\Shipping\GlsTrackingService;
use App\Services\Shipping\OrderTrackingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GlsTrackingEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_is_sent_once_when_gls_tracking_number_first_appears(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-09-07 13:45:00');
        $orderId = $this->createOrder();
        $service = app(OrderTrackingService::class);

        $service->apply(Order::findOrFail($orderId), $this->tracking('51', 'Podaci o pošiljci su uneseni u GLS sustav.'));
        $service->apply(Order::findOrFail($orderId), $this->tracking('4', 'Pošiljka je planirana za dostavu tijekom dana.'));

        Mail::assertSent(ShippingTrackingAvailable::class, function (ShippingTrackingAvailable $mail) use ($orderId) {
            return $mail->hasTo('tracking@example.test')
                && (int) $mail->order->id === $orderId
                && $mail->build()->subject === 'Vaša pošiljka je poslana - Antikvarijat Vremeplov';
        });
        Mail::assertSent(ShippingTrackingAvailable::class, 1);

        $this->assertNotNull(DB::table('orders')->where('id', $orderId)->value('shipping_tracking_email_sent_at'));
        $this->assertDatabaseHas('order_history', [
            'order_id' => $orderId,
            'comment' => 'Kupcu poslan email s podacima za praćenje pošiljke. Broj pošiljke: 123456789.',
        ]);
        $this->assertDatabaseHas('order_history', [
            'order_id' => $orderId,
            'comment' => 'Tracking update (GLS): Pošiljka je planirana za dostavu tijekom dana. Broj pošiljke: 123456789.',
        ]);
    }

    public function test_gls_parcel_locker_is_recognized_as_gls_tracking(): void
    {
        $order = new Order([
            'shipping_code' => 'gls_paketomat',
            'shipping_method' => 'GLS paketomat',
        ]);

        $this->assertSame(
            GlsTrackingService::CARRIER,
            app(OrderTrackingService::class)->resolveCarrier($order)
        );
    }

    public function test_existing_gls_tracking_number_does_not_trigger_a_retroactive_email(): void
    {
        Mail::fake();
        $orderId = $this->createOrder();
        DB::table('orders')->where('id', $orderId)->update([
            'tracking_code' => '123456789',
            'shipping_tracking_status_code' => '51',
        ]);

        app(OrderTrackingService::class)->apply(
            Order::findOrFail($orderId),
            $this->tracking('4', 'Pošiljka je planirana za dostavu tijekom dana.')
        );

        Mail::assertNothingSent();
        $this->assertNull(DB::table('orders')->where('id', $orderId)->value('shipping_tracking_email_sent_at'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tracking(string $statusCode, string $status): array
    {
        return [
            'carrier' => GlsTrackingService::CARRIER,
            'parcel_id' => 'PARCEL-123',
            'tracking_code' => '123456789',
            'tracking_url' => 'https://gls.example.test/?match=123456789',
            'status_code' => $statusCode,
            'status' => $status,
            'tracked_at' => now(),
            'payload' => ['test' => true],
        ];
    }

    private function createOrder(): int
    {
        return (int) DB::table('orders')->insertGetId([
            'user_id' => 0,
            'affiliate_id' => 0,
            'order_status_id' => (int) config('settings.order.status.paid', 3),
            'invoice' => null,
            'total' => 19.82,
            'payment_fname' => 'Test',
            'payment_lname' => 'Kupac',
            'payment_address' => 'Test ulica 1',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_phone' => null,
            'payment_email' => 'tracking@example.test',
            'payment_method' => 'Kartice',
            'payment_code' => 'wspay',
            'payment_card' => null,
            'payment_installment' => 0,
            'shipping_fname' => 'Test',
            'shipping_lname' => 'Kupac',
            'shipping_address' => 'Test ulica 1',
            'shipping_zip' => '10000',
            'shipping_city' => 'Zagreb',
            'shipping_phone' => null,
            'shipping_email' => 'tracking@example.test',
            'shipping_method' => 'GLS paketomat',
            'shipping_code' => 'gls_paketomat',
            'shipping_carrier' => GlsTrackingService::CARRIER,
            'company' => '',
            'oib' => '',
            'comment' => null,
            'tracking_code' => '',
            'shipped' => false,
            'printed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
