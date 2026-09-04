<?php

namespace Tests\Feature;

use App\Models\Back\Orders\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerTrackingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_page_requires_signature_and_exposes_no_customer_contact_data(): void
    {
        $order = $this->order();

        $this->get(route('order.tracking.public', $order))->assertForbidden();

        $url = URL::temporarySignedRoute(
            'order.tracking.public',
            now()->addHour(),
            ['order' => $order->id]
        );

        $this->get($url)
            ->assertOk()
            ->assertSee('TRACK-123')
            ->assertSee('Pošiljka je u transportu.')
            ->assertDontSee('private@example.test');
    }

    public function test_unknown_frontend_route_returns_real_404(): void
    {
        $this->get('/stranica-koja-sigurno-ne-postoji-404')->assertNotFound();
    }

    private function order(): Order
    {
        return Order::query()->create([
            'order_status_id' => 4,
            'payment_fname' => 'Privatno',
            'payment_lname' => 'Ime',
            'payment_address' => 'Privatna adresa 1',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_phone' => '0911111111',
            'payment_email' => 'private@example.test',
            'payment_method' => 'Kartica',
            'payment_code' => 'wspay',
            'shipping_fname' => 'Privatno',
            'shipping_lname' => 'Ime',
            'shipping_address' => 'Privatna adresa 1',
            'shipping_zip' => '10000',
            'shipping_city' => 'Zagreb',
            'shipping_phone' => '0911111111',
            'shipping_email' => 'private@example.test',
            'shipping_method' => 'GLS',
            'shipping_code' => 'gls',
            'company' => '',
            'oib' => '',
            'tracking_code' => 'TRACK-123',
            'shipping_carrier' => 'gls',
            'shipping_tracking_url' => 'https://example.test/track/TRACK-123',
            'shipping_tracking_status' => 'Pošiljka je u transportu.',
            'shipping_tracking_updated_at' => now(),
        ]);
    }
}
