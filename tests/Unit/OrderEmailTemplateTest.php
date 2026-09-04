<?php

namespace Tests\Unit;

use App\Mail\OrderSent;
use App\Mail\OrderStatusChanged;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderProduct;
use App\Models\Back\Orders\OrderTotal;
use Tests\TestCase;

class OrderEmailTemplateTest extends TestCase
{
    public function test_customer_emails_render_from_order_snapshot_and_include_secure_tracking_link(): void
    {
        $order = new Order([
            'id' => 4321,
            'payment_fname' => 'Ana',
            'payment_lname' => 'Anić',
            'payment_address' => 'Ilica 1',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_email' => 'ana@example.test',
            'payment_phone' => '0911111111',
            'payment_code' => 'wspay',
            'shipping_method' => 'GLS',
            'shipping_state' => 'Croatia',
            'tracking_code' => 'GLS-TRACK-1',
            'shipping_tracking_url' => 'https://example.test/track/GLS-TRACK-1',
            'total' => 25.50,
        ]);
        $order->id = 4321;

        $product = new OrderProduct([
            'product_id' => 99,
            'name' => 'Knjiga iz snimke narudžbe',
            'quantity' => 1,
            'price' => 25.50,
            'total' => 25.50,
        ]);
        $product->setRelation('product', null);
        $order->setRelation('products', collect([$product]));
        $order->setRelation('totals', collect([
            new OrderTotal(['code' => 'total', 'title' => 'Sveukupno', 'value' => 25.50]),
        ]));

        $confirmation = (new OrderSent($order))->render();
        $status = (new OrderStatusChanged($order, (object) ['title' => 'Poslano']))->render();

        $this->assertStringContainsString('Knjiga iz snimke narudžbe', $confirmation);
        $this->assertStringContainsString('GLS-TRACK-1', $status);
        $this->assertStringContainsString('/narudzba/4321/pracenje?', $status);
        $this->assertStringContainsString('signature=', $status);
    }
}
