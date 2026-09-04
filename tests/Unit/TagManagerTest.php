<?php

namespace Tests\Unit;

use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderProduct;
use App\Models\TagManager;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TagManagerTest extends TestCase
{
    public function test_purchase_keeps_large_decimal_value_and_uses_order_snapshot_when_product_was_deleted(): void
    {
        $product = new OrderProduct([
            'product_id' => 77,
            'name' => 'Rasprodana knjiga',
            'quantity' => 3,
            'price' => 420.15,
            'discount' => -10,
        ]);
        $product->setRelation('real', null);

        $order = new TagManagerTestOrder(['id' => 123, 'total' => 1260.45]);
        $order->setRelation('products', collect([$product]));
        $order->testTotals = collect([
            (object) ['code' => 'subtotal', 'value' => 1250.45],
            (object) ['code' => 'shipping', 'value' => 10],
        ]);

        $data = TagManager::getGoogleSuccessDataLayer($order);

        $this->assertSame(1260.45, $data['ecommerce']['value']);
        $this->assertSame(3, $data['ecommerce']['items'][0]['quantity']);
        $this->assertSame('Rasprodana knjiga', $data['ecommerce']['items'][0]['item_name']);
        $this->assertSame(420.15, $data['ecommerce']['items'][0]['price']);
    }

    public function test_cart_event_uses_actual_cart_quantity(): void
    {
        $item = (object) [
            'quantity' => 4,
            'associatedModel' => (object) [
                'dataLayer' => ['item_id' => 'SKU-1', 'quantity' => 1],
            ],
        ];

        $items = TagManager::getGoogleCartDataLayer(['items' => [$item]]);

        $this->assertSame(4, $items[0]['quantity']);
    }
}

class TagManagerTestOrder extends Order
{
    /** @var Collection */
    public $testTotals;

    public function totals()
    {
        return new class($this->testTotals) {
            /** @var Collection */
            private $totals;

            public function __construct(Collection $totals)
            {
                $this->totals = $totals;
            }

            public function get(): Collection
            {
                return $this->totals;
            }
        };
    }
}
