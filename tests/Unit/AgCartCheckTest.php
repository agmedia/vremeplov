<?php

namespace Tests\Unit;

use App\Helpers\Session\CheckoutSession;
use App\Models\Front\AgCart;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgCartCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('products');
        Schema::create('products', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->integer('quantity')->default(0);
            $table->boolean('status')->default(true);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->timestamp('inventory_reserved_at')->nullable();
            $table->timestamp('inventory_committed_at')->nullable();
            $table->timestamp('inventory_released_at')->nullable();
            $table->unsignedInteger('inventory_reservation_version')->default(0);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('reservation_version');
            $table->string('action');
            $table->unsignedInteger('quantity');
        });

        CheckoutSession::forgetOrder();
    }

    protected function tearDown(): void
    {
        CheckoutSession::forgetOrder();
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');

        parent::tearDown();
    }

    public function test_check_always_returns_cart_and_message(): void
    {
        $cart = new CartForStockCheckTest($this->newCartId());

        $response = $cart->check(new Request(['ids' => []]));

        $this->assertSame(['cart', 'message'], array_keys($response));
        $this->assertSame(0, $response['cart']['count']);
        $this->assertNull($response['message']);
    }

    public function test_check_removes_missing_out_of_stock_and_inactive_products(): void
    {
        DB::table('products')->insert([
            ['id' => 1, 'name' => 'Rasprodana knjiga', 'quantity' => 0, 'status' => true],
            ['id' => 2, 'name' => 'Neaktivna knjiga', 'quantity' => 4, 'status' => false],
        ]);

        $cartId = $this->newCartId();
        $this->addCartItem($cartId, 1, 'Rasprodana knjiga', 1);
        $this->addCartItem($cartId, 2, 'Neaktivna knjiga', 1);
        $this->addCartItem($cartId, 3, 'Obrisana knjiga', 1);

        $response = (new CartForStockCheckTest($cartId))->check(new Request([
            'ids' => [1],
        ]));

        $this->assertSame(0, $response['cart']['count']);
        $this->assertCount(0, $response['cart']['items']);
        $this->assertStringContainsString('Rasprodana knjiga', $response['message']);
        $this->assertStringContainsString('Neaktivna knjiga', $response['message']);
        $this->assertStringContainsString('Obrisana knjiga', $response['message']);
    }

    public function test_check_caps_cart_quantity_to_available_stock_with_an_absolute_update(): void
    {
        DB::table('products')->insert([
            'id' => 10,
            'name' => 'Knjiga s jednim primjerkom',
            'quantity' => 1,
            'status' => true,
        ]);

        $cartId = $this->newCartId();
        $this->addCartItem($cartId, 10, 'Knjiga s jednim primjerkom', 3);

        // Even an incomplete browser payload must not bypass the stock check.
        $response = (new CartForStockCheckTest($cartId))->check(new Request([
            'ids' => [],
        ]));

        $item = $response['cart']['items']->firstWhere('id', 10);

        $this->assertSame(1, $response['cart']['count']);
        $this->assertSame(1, (int) $item->quantity);
        $this->assertStringContainsString('smanjena je na 1', $response['message']);
    }

    public function test_check_includes_stock_reserved_by_the_current_checkout_order(): void
    {
        DB::table('products')->insert([
            'id' => 20,
            'name' => 'Zadnji rezervirani primjerak',
            'quantity' => 0,
            'status' => true,
        ]);
        DB::table('orders')->insert([
            'id' => 500,
            'inventory_reserved_at' => now(),
            'inventory_committed_at' => null,
            'inventory_released_at' => null,
            'inventory_reservation_version' => 1,
        ]);
        DB::table('inventory_movements')->insert([
            'order_id' => 500,
            'product_id' => 20,
            'reservation_version' => 1,
            'action' => 'reserve',
            'quantity' => 1,
        ]);
        CheckoutSession::setOrder(['id' => 500]);

        $cartId = $this->newCartId();
        $this->addCartItem($cartId, 20, 'Zadnji rezervirani primjerak', 1);

        $response = (new CartForStockCheckTest($cartId))->check(new Request([
            'ids' => [20],
        ]));

        $this->assertSame(1, $response['cart']['count']);
        $this->assertSame(20, (int) $response['cart']['items']->first()->id);
        $this->assertNull($response['message']);
    }

    private function newCartId(): string
    {
        return 'stock-check-' . uniqid('', true);
    }

    private function addCartItem(string $cartId, int $id, string $name, int $quantity): void
    {
        Cart::session($cartId)->add([
            'id' => $id,
            'name' => $name,
            'price' => 10,
            'quantity' => $quantity,
            'attributes' => [],
        ]);
    }
}

class CartForStockCheckTest extends AgCart
{
    public function get()
    {
        $items = $this->getCartItems();

        return [
            'items' => $items,
            'count' => (int) $items->sum('quantity'),
        ];
    }
}
