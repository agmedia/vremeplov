<?php

namespace Tests\Feature;

use App\Helpers\Helper;
use App\Http\Controllers\Api\v2\CartController;
use App\Models\Back\Marketing\Action;
use App\Models\Front\AgCart;
use App\Models\Front\Catalog\ProductAction;
use Carbon\Carbon;
use Darryldecode\Cart\CartCondition;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionProperty;
use Tests\TestCase;

class NewsletterCouponTest extends TestCase
{
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'newsletter_coupon_testing',
            'database.connections.newsletter_coupon_testing' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('newsletter_coupon_testing');

        Schema::create('product_actions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('type');
            $table->decimal('discount', 15, 4);
            $table->string('group');
            $table->text('links')->nullable();
            $table->dateTime('date_start')->nullable();
            $table->dateTime('date_end')->nullable();
            $table->text('data')->nullable();
            $table->string('coupon')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->boolean('status')->default(false);
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->decimal('price', 15, 4);
            $table->decimal('special', 15, 4)->nullable();
            $table->unsignedBigInteger('action_id')->nullable();
            $table->dateTime('special_from')->nullable();
            $table->dateTime('special_to')->nullable();
        });
        $this->freezeCouponTime('2026-10-09 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('newsletter_coupon_testing');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    public function test_coupon_is_valid_through_the_last_second_in_zagreb_and_expires_at_midnight(): void
    {
        $action = $this->action();
        $cart = $this->cart();

        foreach (['2026-10-09 00:00:00', '2026-10-09 23:59:59'] as $time) {
            $this->freezeCouponTime($time);
            $this->assertTrue($action->isValid('VREMEPLOV20'));
            $this->assertTrue(ProductAction::active()->where('coupon', 'VREMEPLOV20')->exists());
            $this->assertInstanceOf(CartCondition::class, Helper::hasCouponCartConditions($cart, 'VREMEPLOV20'));
        }

        $this->freezeCouponTime('2026-10-10 00:00:00');
        $this->assertFalse($action->isValid('VREMEPLOV20'));
        $this->assertFalse(ProductAction::active()->where('coupon', 'VREMEPLOV20')->exists());
        $this->assertFalse(Helper::hasCouponCartConditions($cart, 'VREMEPLOV20'));
        $this->assertSame('Europe/Zagreb', now()->timezoneName);
    }

    /** @dataProvider invalidCoupons */
    public function test_invalid_coupon_cannot_be_accepted_or_discount_the_cart(array $attributes): void
    {
        $action = $this->action($attributes);

        $this->assertFalse($action->isValid('VREMEPLOV20'));
        $this->assertFalse(ProductAction::active()->where('coupon', 'VREMEPLOV20')->exists());
        $this->assertFalse(Helper::hasCouponCartConditions($this->cart(), 'VREMEPLOV20'));
    }

    public function invalidCoupons(): array
    {
        return [
            'disabled' => [['status' => 0]],
            'wrong code' => [['coupon' => 'OTHER20']],
            'future' => [['date_start' => '2026-10-10 00:00:00']],
            'expired' => [['date_end' => '2026-10-08 23:59:59']],
        ];
    }

    public function test_open_dates_do_not_bypass_status_start_date_or_the_requested_code(): void
    {
        $this->action(['coupon' => 'OTHER20', 'date_end' => null]);
        $this->action(['status' => 0, 'date_start' => null, 'date_end' => null]);
        $this->action(['date_start' => '2026-10-10 00:00:00', 'date_end' => null]);

        $this->assertFalse(ProductAction::active()->where('coupon', 'VREMEPLOV20')->exists());
        $this->assertFalse(Helper::hasCouponCartConditions($this->cart(), 'VREMEPLOV20'));

        $this->action(['date_start' => null, 'date_end' => null]);
        $this->assertSame(1, ProductAction::active()->where('coupon', 'VREMEPLOV20')->count());
        $this->assertInstanceOf(CartCondition::class, Helper::hasCouponCartConditions($this->cart(), 'VREMEPLOV20'));
    }

    public function test_creating_a_total_coupon_preserves_existing_product_promotions_and_full_datetimes(): void
    {
        $before = $this->promotedProduct();
        $action = (new Action())->validateRequest($this->request())->create();

        $this->assertSame('total', $action->group);
        $this->assertSame('2026-10-09 00:00:00', $action->date_start);
        $this->assertSame('2026-10-09 23:59:59', $action->date_end);
        $this->assertEquals($before, DB::table('products')->first());
    }

    public function test_editing_a_total_coupon_preserves_existing_product_promotions_and_full_datetimes(): void
    {
        $before = $this->promotedProduct();
        $action = $this->action(['discount' => 10, 'date_end' => '2026-10-08 23:59:59']);
        $action->validateRequest($this->request())->edit();

        $action->refresh();
        $this->assertEquals(20, $action->discount);
        $this->assertSame('2026-10-09 23:59:59', $action->date_end);
        $this->assertEquals($before, DB::table('products')->first());
    }

    public function test_twenty_percent_uses_the_promoted_product_subtotal_and_leaves_shipping_undiscounted(): void
    {
        $this->action();
        $cart = $this->cart();
        $cart->addItemCondition(1, new CartCondition([
            'name' => 'Existing product promotion', 'type' => 'promo', 'value' => '-20',
        ]));
        $this->assertEquals(80, $cart->getSubTotal());

        // AgCart calculates the coupon before adding shipping/payment conditions.
        $coupon = Helper::hasCouponCartConditions($cart, 'VREMEPLOV20');
        $this->assertEquals(-16, (float) $coupon->getValue());
        $this->assertSame(['type' => 'coupon', 'description' => 'VREMEPLOV20'], $coupon->getAttributes());
        $cart->condition(new CartCondition([
            'name' => 'Shipping', 'type' => 'shipping', 'target' => 'total', 'value' => '+5',
        ]));
        $cart->condition($coupon);

        $this->assertEquals(80, $cart->getSubTotal());
        $this->assertEquals(69, $cart->getTotal());
        $this->assertCount(1, $cart->get(1)->conditions);
        $this->assertEquals(100, $cart->get(1)->price);
    }

    public function test_coupon_cannot_be_saved_with_its_end_before_its_start(): void
    {
        $request = $this->request();
        $request->merge(['date_end' => '2026-10-08T23:59:59']);

        $this->expectException(ValidationException::class);
        (new Action())->validateRequest($request);
    }

    /** @dataProvider acceptedCouponInputs */
    public function test_applying_a_coupon_immediately_updates_the_cart_and_session_with_its_canonical_code(string $input): void
    {
        $this->action();
        [$cart, $contents, $sessionKey] = $this->agCart();

        $this->assertSame(1, $cart->coupon($input));
        $this->assertSame('VREMEPLOV20', $this->cartCoupon($cart));
        $this->assertSame('VREMEPLOV20', session($sessionKey));
        $this->assertTrue($contents->getContent()->isEmpty());
    }

    public function acceptedCouponInputs(): array
    {
        return [
            'canonical' => ['VREMEPLOV20'],
            'lowercase' => ['vremeplov20'],
            'surrounding whitespace' => ['  vremeplov20  '],
        ];
    }

    /** @dataProvider invalidCoupons */
    public function test_rejected_coupon_preserves_the_previous_coupon_session_and_cart_items(array $attributes): void
    {
        $this->action($attributes);
        [$cart, $contents, $sessionKey] = $this->agCart('KEEP20');
        $contents->add([
            'id' => 1, 'name' => 'Book already in cart', 'price' => 100, 'quantity' => 2,
            'attributes' => ['existing' => true],
            'conditions' => new CartCondition([
                'name' => 'Existing promotion', 'type' => 'promo', 'value' => '-20',
            ]),
        ]);
        $before = $contents->getContent()->toArray();

        $this->assertSame(0, $cart->coupon('VREMEPLOV20'));
        $this->assertSame('KEEP20', $this->cartCoupon($cart));
        $this->assertSame('KEEP20', session($sessionKey));
        $this->assertEquals($before, $contents->getContent()->toArray());
        $this->assertEquals(160, $contents->getSubTotal());
    }

    /** @dataProvider clearedCouponInputs */
    public function test_removing_a_coupon_clears_the_code_and_forgets_the_session($input): void
    {
        [$cart, $contents, $sessionKey] = $this->agCart('VREMEPLOV20');

        $this->assertSame(1, $cart->coupon($input));
        $this->assertSame('', $this->cartCoupon($cart));
        $this->assertFalse(session()->has($sessionKey));
        $this->assertTrue($contents->getContent()->isEmpty());
    }

    public function clearedCouponInputs(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'whitespace' => ['   '],
            'literal null' => ['null'],
            'uppercase literal with whitespace' => ['  NULL  '],
        ];
    }

    public function test_controller_does_not_overwrite_a_previous_coupon_before_rejecting_an_invalid_code(): void
    {
        $this->action(['status' => 0]);
        [$cart, , $sessionKey] = $this->agCart('KEEP20');
        $controller = new CartController();
        foreach (['cart' => $cart, 'key' => substr($sessionKey, 0, -strlen('_coupon'))] as $name => $value) {
            $property = new ReflectionProperty(CartController::class, $name);
            $property->setAccessible(true);
            $property->setValue($controller, $value);
        }

        $response = $controller->coupon('VREMEPLOV20');

        $this->assertSame(0, $response->getData());
        $this->assertSame('KEEP20', session($sessionKey));
        $this->assertSame('KEEP20', $this->cartCoupon($cart));
    }

    private function action(array $attributes = []): Action
    {
        $id = DB::table('product_actions')->insertGetId(array_merge([
            'title' => 'Newsletter 20%', 'type' => 'P', 'discount' => 20, 'group' => 'total',
            'coupon' => 'VREMEPLOV20', 'status' => 1,
            'date_start' => '2026-10-09 00:00:00', 'date_end' => '2026-10-09 23:59:59',
        ], $attributes));

        return Action::findOrFail($id);
    }

    private function request(): Request
    {
        return new Request([
            'title' => 'Newsletter 20%', 'type' => 'P', 'discount' => 20, 'group' => 'total',
            'coupon' => 'VREMEPLOV20', 'status' => 'on',
            'date_start' => '2026-10-09T00:00:00', 'date_end' => '2026-10-09T23:59:59',
        ]);
    }

    private function promotedProduct(): object
    {
        DB::table('products')->insert([
            'price' => 100, 'special' => 80, 'action_id' => 42,
            'special_from' => '2026-10-01 00:00:00', 'special_to' => '2026-10-31 23:59:59',
        ]);

        return DB::table('products')->first();
    }

    private function cart()
    {
        $cart = Cart::session('newsletter-coupon-' . uniqid('', true));
        $cart->add(['id' => 1, 'name' => 'Book', 'price' => 100, 'quantity' => 1, 'attributes' => []]);

        return $cart;
    }

    private function agCart(?string $existingCoupon = null): array
    {
        $id = 'newsletter-coupon-change-' . uniqid('', true);
        $sessionKey = (config('session.cart') ?: 'agm') . '_coupon';
        if ($existingCoupon === null) {
            session()->forget($sessionKey);
        } else {
            session()->put($sessionKey, $existingCoupon);
        }

        return [new AgCart($id), Cart::session($id), $sessionKey];
    }

    private function cartCoupon(AgCart $cart): string
    {
        $property = new ReflectionProperty(AgCart::class, 'coupon');
        $property->setAccessible(true);

        return $property->getValue($cart);
    }

    private function freezeCouponTime(string $time): void
    {
        Carbon::setTestNow(Carbon::parse($time, 'Europe/Zagreb'));
    }
}
