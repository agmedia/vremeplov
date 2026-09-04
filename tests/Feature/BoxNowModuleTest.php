<?php

namespace Tests\Feature;

use App\Helpers\Helper;
use App\Http\Controllers\Back\OrderController;
use App\Http\Controllers\Back\Settings\App\ShippingController;
use App\Http\Controllers\Back\Settings\SettingsController;
use App\Models\Back\Orders\Order;
use App\Services\Shipping\BoxNowOrderPolicy;
use App\Services\Shipping\BoxNowService;
use App\Services\Shipping\BoxNowSettingsService;
use App\Services\Shipping\OrderTrackingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class BoxNowModuleTest extends TestCase
{
    /** @var string */
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'boxnow_testing',
            'database.connections.boxnow_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
        ]);
        DB::purge('boxnow_testing');
        Cache::clear();

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        DB::purge('boxnow_testing');
        config(['database.default' => $this->originalConnection]);

        parent::tearDown();
    }

    public function test_settings_encrypt_secret_and_blank_update_keeps_it(): void
    {
        $settings = new BoxNowSettingsService();
        $payload = $this->settings();

        $this->assertTrue($settings->save($payload));

        $storedValue = (string) DB::table('settings')
            ->where('code', 'shipping')
            ->where('key', 'boxnow_api')
            ->value('value');

        $this->assertStringNotContainsString('top-secret', $storedValue);
        $this->assertSame('top-secret', (new BoxNowSettingsService())->get()['client_secret']);

        $payload['client_id'] = 'changed-client';
        $payload['client_secret'] = '';
        $payload['cod_enabled'] = true;
        $this->assertTrue((new BoxNowSettingsService())->save($payload));

        $resolved = (new BoxNowSettingsService())->get();
        $this->assertSame('changed-client', $resolved['client_id']);
        $this->assertSame('top-secret', $resolved['client_secret']);
        $this->assertTrue($resolved['cod_enabled']);
        $this->assertArrayNotHasKey('client_secret', (new BoxNowSettingsService())->adminValues());
    }

    public function test_public_settings_endpoint_never_exposes_boxnow_api_configuration(): void
    {
        DB::table('settings')->insert([
            [
                'code' => 'currency',
                'key' => 'list',
                'value' => json_encode([['code' => 'EUR', 'main' => true]]),
                'json' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'shipping',
                'key' => 'list.boxnow',
                'value' => json_encode([['code' => 'boxnow', 'status' => false]]),
                'json' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'shipping',
                'key' => 'boxnow_api',
                'value' => json_encode([
                    'client_id' => 'private-client-marker',
                    'client_secret_encrypted' => 'private-ciphertext-marker',
                ]),
                'json' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Helper::resolveCache('set')->put('cart', [
            'shipping.boxnow_api' => [
                'client_secret_encrypted' => 'legacy-cached-ciphertext-marker',
            ],
        ], 600);

        $response = (new SettingsController())->get();

        $this->assertArrayHasKey('currency.list', $response);
        $this->assertArrayHasKey('shipping.list.boxnow', $response);
        $this->assertArrayNotHasKey('shipping.boxnow_api', $response);
        $serialized = json_encode($response);
        $this->assertStringNotContainsString('private-client-marker', $serialized);
        $this->assertStringNotContainsString('private-ciphertext-marker', $serialized);
        $this->assertStringNotContainsString('legacy-cached-ciphertext-marker', $serialized);
    }

    public function test_admin_dispatch_persists_tracking_and_second_request_does_not_repost(): void
    {
        $this->insertBoxNowOrder();
        $settings = new FeatureStubBoxNowSettingsService($this->settings());
        $boxNow = new BoxNowService($settings);
        $trackingService = new OrderTrackingService($boxNow);
        $policy = new BoxNowOrderPolicy($settings);
        $controller = new OrderController();
        $deliveryPosts = 0;

        Http::fake(function (HttpRequest $request) use (&$deliveryPosts) {
            if ($request->url() === 'https://boxnow.example.test/api/v1/auth-sessions') {
                return Http::response(['access_token' => 'boxnow-token'], 200);
            }

            if ($request->url() === 'https://boxnow.example.test/api/v1/delivery-requests') {
                $deliveryPosts++;

                return Http::response([
                    'parcels' => [['id' => 'BOX-LIVE-100']],
                    'requestId' => 'REQ-1',
                ], 200);
            }

            return Http::response([], 404);
        });

        $first = $controller->api_send_boxnow(
            new Request(['order_id' => 100]),
            $boxNow,
            $trackingService,
            $policy
        );
        $second = $controller->api_send_boxnow(
            new Request(['order_id' => 100]),
            $boxNow,
            $trackingService,
            $policy
        );

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(200, $second->getStatusCode());
        $this->assertStringContainsString('BOX-LIVE-100', (string) data_get($first->getData(true), 'message'));
        $this->assertStringContainsString('već kreirana', (string) data_get($second->getData(true), 'message'));
        $this->assertSame(1, $deliveryPosts);

        $order = Order::query()->findOrFail(100);
        $this->assertSame('boxnow', $order->shipping_carrier);
        $this->assertSame('BOX-LIVE-100', $order->shipping_parcel_id);
        $this->assertSame('BOX-LIVE-100', $order->tracking_code);
        $this->assertSame('new', $order->shipping_tracking_status_code);
        $this->assertSame('REQ-1', $order->shipping_tracking_payload['requestId']);
        $this->assertSame(1, DB::table('order_history')->where('order_id', 100)->count());

        Http::assertSent(function (HttpRequest $request) {
            return $request->url() === 'https://boxnow.example.test/api/v1/delivery-requests'
                && $request['destination']['locationId'] === 'LOCKER-ZG-1';
        });
    }

    public function test_boxnow_dispatch_route_requires_authentication(): void
    {
        $this->postJson(route('api.order.send.boxnow'), ['order_id' => 100])
            ->assertUnauthorized();
    }

    public function test_parallel_dispatch_is_rejected_before_remote_api_call(): void
    {
        $this->insertBoxNowOrder();
        $settings = new FeatureStubBoxNowSettingsService($this->settings());
        $boxNow = new BoxNowService($settings);
        $trackingService = new OrderTrackingService($boxNow);
        $policy = new BoxNowOrderPolicy($settings);
        $lock = Cache::lock('boxnow-shipment-create:100', 180);
        $this->assertTrue($lock->get());

        try {
            Http::fake();
            $response = (new OrderController())->api_send_boxnow(
                new Request(['order_id' => 100]),
                $boxNow,
                $trackingService,
                $policy
            );

            $this->assertSame(409, $response->getStatusCode());
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_dispatch_rechecks_carrier_after_acquiring_lock(): void
    {
        $this->insertBoxNowOrder();
        $settings = new FeatureStubBoxNowSettingsService($this->settings());
        $boxNow = new BoxNowService($settings);
        $trackingService = new FlippingBoxNowOrderTrackingService($boxNow);
        Http::fake();

        $response = (new OrderController())->api_send_boxnow(
            new Request(['order_id' => 100]),
            $boxNow,
            $trackingService,
            new BoxNowOrderPolicy($settings)
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString(
            'više nema odabranu',
            (string) data_get($response->getData(true), 'error')
        );
        Http::assertNothingSent();
    }

    public function test_status_change_is_rejected_while_boxnow_dispatch_is_in_progress(): void
    {
        $this->insertBoxNowOrder();
        $lock = Cache::lock('boxnow-shipment-create:100', 180);
        $this->assertTrue($lock->get());

        try {
            $response = (new OrderController())->api_status_change(new Request([
                'order_id' => 100,
                'status' => 5,
            ]));

            $this->assertSame(409, $response->getStatusCode());
            $this->assertSame(3, (int) DB::table('orders')->where('id', 100)->value('order_status_id'));
            $this->assertSame(0, DB::table('order_history')->where('order_id', 100)->count());
        } finally {
            $lock->release();
        }
    }

    public function test_manual_refresh_marks_delivered_order_as_shipped(): void
    {
        $this->insertBoxNowOrder();
        DB::table('orders')->where('id', 100)->update([
            'shipping_carrier' => 'boxnow',
            'shipping_parcel_id' => 'BOX-DELIVERED',
            'tracking_code' => 'BOX-DELIVERED',
        ]);
        $settings = new FeatureStubBoxNowSettingsService($this->settings());
        $boxNow = new BoxNowService($settings);
        $trackingService = new OrderTrackingService($boxNow);

        Http::fake(function (HttpRequest $request) {
            if ($request->url() === 'https://boxnow.example.test/api/v1/auth-sessions') {
                return Http::response(['access_token' => 'boxnow-token'], 200);
            }

            return Http::response([
                'data' => [['id' => 'BOX-DELIVERED', 'state' => 'delivered']],
            ], 200);
        });

        $response = (new OrderController())->api_refresh_boxnow_tracking(
            new Request(['order_id' => 100]),
            $trackingService
        );
        $order = Order::query()->findOrFail(100);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue((bool) $order->shipped);
        $this->assertSame('delivered', $order->shipping_tracking_status_code);
        $this->assertSame('Pošiljka je preuzeta.', $order->shipping_tracking_status);
    }

    public function test_tracking_response_without_state_preserves_last_known_status(): void
    {
        $this->insertBoxNowOrder();
        DB::table('orders')->where('id', 100)->update([
            'shipping_carrier' => 'boxnow',
            'shipping_parcel_id' => 'BOX-NO-STATE',
            'tracking_code' => 'BOX-NO-STATE',
            'shipping_tracking_status_code' => 'intransit',
            'shipping_tracking_status' => 'Pošiljka se dostavlja.',
            'shipping_tracking_updated_at' => now()->subHour(),
        ]);
        $settings = new FeatureStubBoxNowSettingsService($this->settings());
        $trackingService = new OrderTrackingService(new BoxNowService($settings));

        Http::fakeSequence()
            ->push(['access_token' => 'boxnow-token'], 200)
            ->push(['data' => [['id' => 'BOX-NO-STATE']]], 200);

        $trackingService->refreshBoxNow(Order::query()->findOrFail(100));
        $order = Order::query()->findOrFail(100);

        $this->assertSame('intransit', $order->shipping_tracking_status_code);
        $this->assertSame('Pošiljka se dostavlja.', $order->shipping_tracking_status);
        $this->assertNotNull($order->shipping_tracking_attempted_at);
    }

    public function test_parallel_tracking_refresh_is_skipped_before_remote_api_call(): void
    {
        $this->insertBoxNowOrder();
        DB::table('orders')->where('id', 100)->update([
            'shipping_carrier' => 'boxnow',
            'shipping_parcel_id' => 'BOX-LOCKED',
        ]);
        $lock = Cache::lock('boxnow-tracking-refresh:100', 90);
        $this->assertTrue($lock->get());

        try {
            Http::fake();
            $service = new OrderTrackingService(new BoxNowService(
                new FeatureStubBoxNowSettingsService($this->settings())
            ));
            $result = $service->refreshBoxNow(Order::query()->findOrFail(100));

            $this->assertFalse($result['updated']);
            $this->assertStringContainsString('već je u tijeku', $result['message']);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_explicit_other_carrier_is_not_inferred_as_boxnow_by_scheduler(): void
    {
        $this->insertBoxNowOrder();
        DB::table('orders')->where('id', 100)->update([
            'shipping_carrier' => 'gls',
            'tracking_code' => 'GLS-100',
            'shipping_code' => 'boxnow',
            'shipping_tracking_status_code' => null,
            'shipping_tracking_attempted_at' => null,
        ]);
        Http::fake();

        $order = Order::query()->findOrFail(100);
        $trackingService = new OrderTrackingService(
            new BoxNowService(new FeatureStubBoxNowSettingsService($this->settings()))
        );

        $this->assertFalse($trackingService->isBoxNowOrder($order));
        $this->artisan('sync:boxnow-tracking', ['--limit' => 50, '--stale-minutes' => 15])
            ->expectsOutput('Box Now tracking osvježen. Ažurirano: 0. Neuspjelo: 0.')
            ->assertExitCode(0);
        Http::assertNothingSent();
    }

    public function test_blocked_order_statuses_never_call_boxnow_api(): void
    {
        $this->insertBoxNowOrder();
        config([
            'settings.order.status.unfinished' => 8,
            'settings.order.status.declined' => 7,
            'settings.order.status.canceled' => 5,
            'settings.order.status.refund' => 12,
            'settings.order.status.blacklist' => 14,
        ]);
        Http::fake();

        $settings = new FeatureStubBoxNowSettingsService($this->settings());
        $boxNow = new BoxNowService($settings);
        $trackingService = new OrderTrackingService($boxNow);
        $policy = new BoxNowOrderPolicy($settings);
        $controller = new OrderController();

        foreach ([1, 2, 4, 5, 6, 7, 8, 9, 10, 12, 13, 14] as $status) {
            DB::table('orders')->where('id', 100)->update(['order_status_id' => $status]);

            $response = $controller->api_send_boxnow(
                new Request(['order_id' => 100]),
                $boxNow,
                $trackingService,
                $policy
            );

            $this->assertSame(422, $response->getStatusCode());
        }

        Http::assertNothingSent();
        $this->assertSame(0, DB::table('order_history')->where('order_id', 100)->count());
        $this->assertNull(DB::table('orders')->where('id', 100)->value('shipping_parcel_id'));
    }

    public function test_legacy_gls_endpoint_cannot_dispatch_boxnow_order(): void
    {
        $this->insertBoxNowOrder();
        Http::fake();

        $response = (new OrderController())->api_send_gls(new Request(['order_id' => 100]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('nema odabranu GLS dostavu', (string) data_get($response->getData(true), 'error'));
        Http::assertNothingSent();
    }

    public function test_gls_dispatch_persists_tracking_and_second_request_does_not_repost(): void
    {
        $this->insertGlsOrder();
        $controller = new FeatureStubGlsOrderController([
            'ParcelIdList' => ['GLS-ID-100'],
            'ParcelNumberList' => ['GLS-NUMBER-100'],
            'PrepareLabelsError' => [],
            'GetPrintedLabelsRequest' => ['Password' => 'must-not-be-stored'],
        ]);

        $first = $controller->api_send_gls(new Request(['order_id' => 101]));
        $second = $controller->api_send_gls(new Request(['order_id' => 101]));

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(200, $second->getStatusCode());
        $this->assertStringContainsString('GLS-NUMBER-100', (string) data_get($first->getData(true), 'message'));
        $this->assertStringContainsString('već kreirana', (string) data_get($second->getData(true), 'message'));
        $this->assertSame(1, $controller->calls);

        $order = Order::query()->findOrFail(101);
        $this->assertSame('gls', $order->shipping_carrier);
        $this->assertSame('GLS-ID-100', $order->shipping_parcel_id);
        $this->assertSame('GLS-NUMBER-100', $order->tracking_code);
        $this->assertTrue((bool) $order->printed);
        $this->assertStringNotContainsString('must-not-be-stored', json_encode($order->shipping_tracking_payload));
        $this->assertSame(1, DB::table('order_history')->where('order_id', 101)->count());
    }

    public function test_parallel_gls_dispatch_is_rejected_before_remote_api_call(): void
    {
        $this->insertGlsOrder();
        $controller = new FeatureStubGlsOrderController(['ParcelIdList' => ['GLS-ID-100']]);
        $lock = Cache::lock('gls-shipment-create:101', 180);
        $this->assertTrue($lock->get());

        try {
            $response = $controller->api_send_gls(new Request(['order_id' => 101]));

            $this->assertSame(409, $response->getStatusCode());
            $this->assertSame(0, $controller->calls);
        } finally {
            $lock->release();
        }
    }

    public function test_admin_modal_contains_all_settings_but_never_secret_value(): void
    {
        $settings = new BoxNowSettingsService();
        $this->assertTrue($settings->save($this->settings()));
        View::share('errors', new ViewErrorBag());

        $html = view('back.settings.app.shipping.modals.boxnow', [
            'geo_zones' => collect(),
            'boxNowSettings' => (new BoxNowSettingsService())->adminValues(),
        ])->render();

        $this->assertStringContainsString('name="base_url"', $html);
        $this->assertStringContainsString('name="client_id"', $html);
        $this->assertStringContainsString('name="client_secret"', $html);
        $this->assertStringContainsString('name="order_prefix"', $html);
        $this->assertStringContainsString('name="tracking_url"', $html);
        $this->assertStringContainsString('name="cod_enabled"', $html);
        $this->assertStringContainsString('name="email_label_on_create"', $html);
        $this->assertStringContainsString('Client Secret je spremljen šifrirano.', $html);
        $this->assertStringNotContainsString('top-secret', $html);
    }

    public function test_opening_shipping_admin_registers_boxnow_method_disabled_by_default(): void
    {
        (new ShippingController())->index(new BoxNowSettingsService());

        $stored = json_decode((string) DB::table('settings')
            ->where('code', 'shipping')
            ->where('key', 'list.boxnow')
            ->value('value'), true);

        $this->assertSame('boxnow', data_get($stored, '0.code'));
        $this->assertFalse((bool) data_get($stored, '0.status'));
        $this->assertSame(0, data_get($stored, '0.data.price'));
    }

    public function test_boxnow_policy_uses_dedicated_parcel_and_cod_admin_switch(): void
    {
        $disabled = new BoxNowOrderPolicy(new FeatureStubBoxNowSettingsService(
            $this->settings(['cod_enabled' => false])
        ));
        $enabled = new BoxNowOrderPolicy(new FeatureStubBoxNowSettingsService(
            $this->settings(['cod_enabled' => true])
        ));
        $order = new Order();
        $order->forceFill([
            'shipping_carrier' => 'boxnow',
            'shipping_parcel_id' => 'DEDICATED-1',
            'tracking_code' => 'LEGACY-1',
            'payment_code' => 'cod',
        ]);

        $this->assertSame('DEDICATED-1', $disabled->parcelId($order));

        foreach ([1, 3, 11] as $status) {
            $order->order_status_id = $status;
            $this->assertFalse($disabled->canDispatch($order));
            $this->assertTrue($enabled->canDispatch($order));
        }

        $order->order_status_id = 5;
        $this->assertFalse($enabled->canDispatch($order));

        $order->payment_code = 'wspay';
        $order->order_status_id = 1;
        $this->assertFalse($enabled->canDispatch($order));
        $order->order_status_id = 3;
        $this->assertTrue($enabled->canDispatch($order));
        $order->order_status_id = 11;
        $this->assertTrue($enabled->canDispatch($order));

        $order->payment_code = 'pickup';
        $this->assertFalse($enabled->canDispatch($order));

        $order->shipping_parcel_id = null;
        $order->shipping_carrier = 'gls';
        $this->assertSame('', $enabled->parcelId($order));
        $order->shipping_carrier = 'boxnow';
        $this->assertSame('LEGACY-1', $enabled->parcelId($order));
    }

    public function test_api_url_allows_only_official_boxnow_v1_https_endpoint(): void
    {
        $settings = new BoxNowSettingsService();

        $this->assertTrue($settings->isAllowedApiUrl('https://api-production.boxnow.hr/api/v1'));
        $this->assertTrue($settings->isAllowedApiUrl('https://api.boxnow.hr/api/v1/'));

        foreach ([
            'http://api-production.boxnow.hr/api/v1',
            'https://boxnow.hr.evil.test/api/v1',
            'https://api-production.boxnow.hr/api/v2',
            'https://api-production.boxnow.hr/api/v1?redirect=evil',
            'https://user:pass@api-production.boxnow.hr/api/v1',
            'https://api-production.boxnow.hr:8443/api/v1',
        ] as $url) {
            $this->assertFalse($settings->isAllowedApiUrl($url), $url);
        }
    }

    private function createSchema(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code');
            $table->string('key');
            $table->text('value');
            $table->boolean('json')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_status_id')->default(1);
            $table->decimal('total', 15, 4)->default(0);
            $table->string('payment_code')->nullable();
            $table->string('payment_fname')->nullable();
            $table->string('payment_lname')->nullable();
            $table->string('payment_phone')->nullable();
            $table->string('payment_email')->nullable();
            $table->string('shipping_fname')->nullable();
            $table->string('shipping_lname')->nullable();
            $table->string('shipping_phone')->nullable();
            $table->string('shipping_email')->nullable();
            $table->string('shipping_method')->nullable();
            $table->string('shipping_code')->nullable();
            $table->text('comment')->nullable();
            $table->text('commentp')->nullable();
            $table->string('tracking_code')->nullable();
            $table->boolean('printed')->default(false);
            $table->boolean('shipped')->default(false);
            $table->string('shipping_carrier')->nullable();
            $table->string('shipping_parcel_id')->nullable();
            $table->string('shipping_tracking_url', 500)->nullable();
            $table->string('shipping_tracking_status_code')->nullable();
            $table->string('shipping_tracking_status')->nullable();
            $table->timestamp('shipping_tracking_updated_at')->nullable();
            $table->timestamp('shipping_tracking_attempted_at')->nullable();
            $table->text('shipping_tracking_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
        });

        Schema::create('order_products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('product_id');
            $table->string('name');
            $table->decimal('total', 15, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('order_history', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('user_id')->default(0);
            $table->unsignedInteger('status')->default(0);
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    private function insertBoxNowOrder(): void
    {
        DB::table('products')->insert(['id' => 7]);
        DB::table('orders')->insert([
            'id' => 100,
            'order_status_id' => 3,
            'total' => 35.90,
            'payment_code' => 'wspay',
            'payment_fname' => 'Marko',
            'payment_lname' => 'Marić',
            'payment_phone' => '091 222 3333',
            'payment_email' => 'marko@example.test',
            'shipping_fname' => 'Marko',
            'shipping_lname' => 'Marić',
            'shipping_phone' => '091 222 3333',
            'shipping_email' => 'marko@example.test',
            'shipping_method' => 'Box Now paketomat',
            'shipping_code' => 'boxnow',
            'commentp' => '10000, Vlaška 1_LOCKER-ZG-1',
            'tracking_code' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('order_products')->insert([
            'order_id' => 100,
            'product_id' => 7,
            'name' => 'Knjiga za test',
            'total' => 35.90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertGlsOrder(): void
    {
        DB::table('orders')->insert([
            'id' => 101,
            'order_status_id' => 3,
            'total' => 42.50,
            'payment_code' => 'cod',
            'payment_fname' => 'Ana',
            'payment_lname' => 'Anić',
            'payment_phone' => '091 111 2222',
            'payment_email' => 'ana@example.test',
            'shipping_fname' => 'Ana',
            'shipping_lname' => 'Anić',
            'shipping_phone' => '091 111 2222',
            'shipping_email' => 'ana@example.test',
            'shipping_method' => 'GLS dostava',
            'shipping_code' => 'gls',
            'tracking_code' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function settings(array $overrides = []): array
    {
        return array_merge([
            'base_url' => 'https://boxnow.example.test/api/v1',
            'client_id' => 'client-id',
            'client_secret' => 'top-secret',
            'api_partner_id' => 'PARTNER-1',
            'widget_partner_id' => 123,
            'order_prefix' => 'VREMEPLOV',
            'warehouse_location_id' => 'WAREHOUSE-1',
            'origin_name' => 'Vremeplov',
            'origin_email' => 'info@example.test',
            'origin_phone' => '+385 1 555 0000',
            'tracking_url' => 'https://track.boxnow.example/{parcel}',
            'allow_return' => true,
            'cod_enabled' => false,
            'email_label_on_create' => true,
        ], $overrides);
    }
}

class FeatureStubBoxNowSettingsService extends BoxNowSettingsService
{
    /** @var array */
    private $values;

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function get(): array
    {
        return $this->values;
    }

    public function isAllowedApiUrl(?string $url = null): bool
    {
        return true;
    }
}

class FlippingBoxNowOrderTrackingService extends OrderTrackingService
{
    /** @var int */
    private $boxNowChecks = 0;

    public function isBoxNowOrder(Order $order): bool
    {
        $this->boxNowChecks++;

        return $this->boxNowChecks === 1;
    }
}

class FeatureStubGlsOrderController extends OrderController
{
    /** @var array */
    private $response;

    /** @var int */
    public $calls = 0;

    public function __construct(array $response)
    {
        $this->response = $response;
    }

    protected function resolveGlsShipment(Order $order): array
    {
        $this->calls++;

        return $this->response;
    }
}
