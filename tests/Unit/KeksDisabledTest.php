<?php

namespace Tests\Unit;

use App\Models\Front\Checkout\PaymentMethod;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KeksDisabledTest extends TestCase
{
    /** @var string */
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'keks_testing',
            'database.connections.keks_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('keks_testing');

        Schema::create('settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code');
            $table->string('key');
            $table->text('value');
            $table->boolean('json')->default(false);
            $table->timestamps();
        });

        $this->storePayment('wspay');
        $this->storePayment('keks');
        $this->storePayment('paypal');
        $this->storePayment('bank');
        $this->storePayment('cod');
        $this->storePayment('pickup');
    }

    protected function tearDown(): void
    {
        DB::purge('keks_testing');
        config(['database.default' => $this->originalConnection]);

        parent::tearDown();
    }

    public function test_keks_cannot_appear_in_checkout_while_integration_is_disabled(): void
    {
        config(['services.keks.enabled' => false]);

        $codes = (new PaymentMethod())->getMethods()->pluck('code');

        $this->assertTrue($codes->contains('wspay'));
        $this->assertFalse($codes->contains('keks'));
    }

    public function test_keks_requires_an_explicit_configuration_switch(): void
    {
        config(['services.keks.enabled' => true]);

        $this->assertTrue(
            (new PaymentMethod())->getMethods()->pluck('code')->contains('keks')
        );
    }

    public function test_pickup_and_international_delivery_allow_only_safe_payment_combinations(): void
    {
        config(['services.keks.enabled' => false]);

        $pickup = $this->paymentMethodsForShipping('pickup');
        $this->assertEqualsCanonicalizing(['pickup', 'wspay', 'paypal'], $pickup);

        $international = $this->paymentMethodsForShipping('gls_world');
        $this->assertNotContains('cod', $international);
        $this->assertContains('wspay', $international);
        $this->assertContains('paypal', $international);

        $domestic = $this->paymentMethodsForShipping('gls');
        $this->assertContains('cod', $domestic);
    }

    private function paymentMethodsForShipping(string $shipping): array
    {
        $methods = new PaymentMethod();
        $property = new \ReflectionProperty(PaymentMethod::class, 'response_methods');
        $property->setAccessible(true);
        $property->setValue($methods, $methods->getMethods()->keyBy('code'));

        return $methods->checkShipping($shipping)->resolve()->keys()->all();
    }

    private function storePayment(string $code): void
    {
        DB::table('settings')->insert([
            'code' => 'payment',
            'key' => 'list.' . $code,
            'value' => json_encode([[
                'title' => strtoupper($code),
                'code' => $code,
                'status' => true,
                'sort_order' => 0,
                'data' => [],
            ]]),
            'json' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
