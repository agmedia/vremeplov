<?php

namespace Tests\Unit;

use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderProduct;
use App\Services\Shipping\BoxNowService;
use App\Services\Shipping\BoxNowSettingsService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class BoxNowServiceTest extends TestCase
{
    public function test_it_authenticates_and_builds_delivery_from_vremeplov_locker_data(): void
    {
        $service = new BoxNowService(new StubBoxNowSettingsService($this->settings([
            'cod_enabled' => true,
        ])));
        $order = $this->order([
            'payment_code' => 'cod',
            'commentp' => '10000, Ilica 1_LOCKER-99',
            'shipping_phone' => '091 123 4567',
        ]);

        Http::fake(function (HttpRequest $request) {
            if ($request->url() === 'https://boxnow.example.test/api/v1/auth-sessions') {
                return Http::response(['access_token' => 'boxnow-token'], 200);
            }

            if ($request->url() === 'https://boxnow.example.test/api/v1/delivery-requests') {
                return Http::response(['parcels' => [['id' => 'BOX-123']]], 200);
            }

            return Http::response([], 404);
        });

        $tracking = $service->createDeliveryRequest($order);

        $this->assertSame('BOX-123', $tracking['parcel_id']);
        $this->assertSame('https://track.boxnow.example/BOX-123', $tracking['tracking_url']);

        Http::assertSent(function (HttpRequest $request) {
            if ($request->url() !== 'https://boxnow.example.test/api/v1/delivery-requests') {
                return false;
            }

            return $request->hasHeader('Authorization', 'Bearer boxnow-token')
                && $request->hasHeader('X-PartnerID', 'PARTNER-1')
                && $request['orderNumber'] === 'VREMEPLOV-72'
                && $request['paymentMode'] === 'cod'
                && $request['amountToBeCollected'] === '42.50'
                && $request['destination']['locationId'] === 'LOCKER-99'
                && $request['destination']['contactNumber'] === '+385911234567'
                && $request['origin']['locationId'] === 'WAREHOUSE-1'
                && $request['items'][0]['id'] === 'VREMEPLOV-72-1'
                && $request['items'][0]['weight'] === 0
                && ! isset($request['compartmentSize'])
                && $request['notifyOnAccepted'] === 'info@example.test';
        });
    }

    public function test_invalid_locker_is_rejected_before_any_http_request(): void
    {
        Http::fake();

        foreach ([
            '',
            'Nema razdjelnika',
            'Adresa_',
            '_LOCKER 1',
            'Adresa_<script>',
            'Adresa_LOCKER/1',
            "Adresa_LOCKER\n1",
        ] as $pickup) {
            $service = new BoxNowService(new StubBoxNowSettingsService($this->settings()));

            try {
                $service->createDeliveryRequest($this->order(['commentp' => $pickup]));
                $this->fail('Neispravan Box Now paketomat mora biti odbijen.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('paketomat nije upisan', $exception->getMessage());
            }
        }

        Http::assertNothingSent();
    }

    public function test_cod_disabled_is_rejected_before_any_http_request(): void
    {
        Http::fake();
        $service = new BoxNowService(new StubBoxNowSettingsService($this->settings([
            'cod_enabled' => false,
        ])));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Plaćanje pouzećem nije omogućeno');

        try {
            $service->createDeliveryRequest($this->order(['payment_code' => 'cod']));
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_pickup_payment_is_rejected_before_any_http_request(): void
    {
        Http::fake();
        $service = new BoxNowService(new StubBoxNowSettingsService($this->settings()));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('osobnog preuzimanja nije dopušteno');

        try {
            $service->createDeliveryRequest($this->order(['payment_code' => 'pickup']));
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_prepaid_delivery_sends_zero_cod_amount(): void
    {
        $service = new BoxNowService(new StubBoxNowSettingsService($this->settings()));

        Http::fakeSequence()
            ->push(['access_token' => 'boxnow-token'], 200)
            ->push(['parcels' => [['id' => 'BOX-PREPAID']]], 200);

        $service->createDeliveryRequest($this->order(['payment_code' => 'wspay']));

        Http::assertSent(function (HttpRequest $request) {
            return $request->url() === 'https://boxnow.example.test/api/v1/delivery-requests'
                && $request['paymentMode'] === 'prepaid'
                && $request['amountToBeCollected'] === '0.00'
                && $request['invoiceValue'] === '42.50';
        });
    }

    public function test_delivery_normalizes_international_and_croatian_trunk_phones(): void
    {
        $service = new BoxNowService(new StubBoxNowSettingsService($this->settings([
            'origin_phone' => '+385 (0)1 555 0000',
        ])));

        Http::fakeSequence()
            ->push(['access_token' => 'boxnow-token'], 200)
            ->push(['parcels' => [['id' => 'BOX-PHONES']]], 200);

        $service->createDeliveryRequest($this->order([
            'shipping_phone' => '0049 30 123 456',
        ]));

        Http::assertSent(function (HttpRequest $request) {
            return $request->url() === 'https://boxnow.example.test/api/v1/delivery-requests'
                && $request['origin']['contactNumber'] === '+38515550000'
                && $request['destination']['contactNumber'] === '+4930123456';
        });
    }

    public function test_boxnow_cod_contract_error_is_returned_clearly(): void
    {
        $service = new BoxNowService(new StubBoxNowSettingsService($this->settings([
            'cod_enabled' => true,
        ])));

        Http::fakeSequence()
            ->push(['access_token' => 'boxnow-token'], 200)
            ->push([
                'error' => [
                    'code' => 'P411',
                    'message' => 'COD is not enabled for this partner.',
                ],
            ], 422);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('COD is not enabled for this partner.');
        $service->createDeliveryRequest($this->order(['payment_code' => 'cod']));
    }

    public function test_conflict_recovers_existing_parcel_without_reposting_delivery(): void
    {
        $service = new BoxNowService(new StubBoxNowSettingsService($this->settings()));
        $order = $this->order();
        $deliveryPosts = 0;

        Http::fake(function (HttpRequest $request) use (&$deliveryPosts) {
            if ($request->url() === 'https://boxnow.example.test/api/v1/auth-sessions') {
                return Http::response(['access_token' => 'boxnow-token'], 200);
            }

            if ($request->url() === 'https://boxnow.example.test/api/v1/delivery-requests') {
                $deliveryPosts++;

                return Http::response(['error' => ['code' => 'P410', 'message' => 'Order number conflict']], 409);
            }

            if (str_starts_with($request->url(), 'https://boxnow.example.test/api/v1/parcels')) {
                return Http::response([
                    'data' => [['id' => 'BOX-RECOVERED', 'state' => 'in-transit']],
                ], 200);
            }

            return Http::response([], 404);
        });

        $tracking = $service->createDeliveryRequest($order);

        $this->assertTrue($tracking['recovered']);
        $this->assertSame('BOX-RECOVERED', $tracking['parcel_id']);
        $this->assertSame(1, $deliveryPosts);
    }

    public function test_tracking_maps_delivered_status(): void
    {
        $service = new BoxNowService(new StubBoxNowSettingsService($this->settings()));
        $order = $this->order(['shipping_parcel_id' => 'BOX-DELIVERED']);

        Http::fake(function (HttpRequest $request) {
            if ($request->url() === 'https://boxnow.example.test/api/v1/auth-sessions') {
                return Http::response(['access_token' => 'boxnow-token'], 200);
            }

            return Http::response([
                'data' => [[
                    'id' => 'BOX-DELIVERED',
                    'events' => [
                        ['event' => 'in-transit', 'createTime' => '2026-08-30T10:00:00Z'],
                        ['event' => 'delivered', 'createTime' => '2026-08-31T10:00:00Z'],
                    ],
                ]],
            ], 200);
        });

        $tracking = $service->track($order);

        $this->assertSame('delivered', $tracking['status_code']);
        $this->assertSame('Pošiljka je preuzeta.', $tracking['status']);
        $this->assertTrue($tracking['is_delivered']);
    }

    public function test_tracking_rejects_empty_parcel_result(): void
    {
        $order = $this->order(['shipping_parcel_id' => 'BOX-EXPECTED']);

        Http::fakeSequence()
            ->push(['access_token' => 'boxnow-token'], 200)
            ->push(['count' => 0, 'data' => []], 200);

        $service = new BoxNowService(new StubBoxNowSettingsService($this->settings()));

        try {
            $service->track($order);
            $this->fail('Prazan Box Now odgovor ne smije biti prihvaćen kao tracking rezultat.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('nije pronašao pošiljku', $exception->getMessage());
        }
    }

    public function test_tracking_rejects_mismatched_parcel_result(): void
    {
        $order = $this->order(['shipping_parcel_id' => 'BOX-EXPECTED']);

        Http::fakeSequence()
            ->push(['access_token' => 'boxnow-token'], 200)
            ->push(['data' => [['id' => 'BOX-OTHER', 'state' => 'intransit']]], 200);

        $service = new BoxNowService(new StubBoxNowSettingsService($this->settings()));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('vratio drugu pošiljku');
        $service->track($order);
    }

    public function test_label_returns_exact_authenticated_pdf(): void
    {
        $pdf = "%PDF-1.7\nBox Now test PDF\n%%EOF";
        $order = $this->order(['shipping_parcel_id' => 'BOX-123']);
        $service = new BoxNowService(new StubBoxNowSettingsService($this->settings()));

        Http::fake(function (HttpRequest $request) use ($pdf) {
            if ($request->url() === 'https://boxnow.example.test/api/v1/auth-sessions') {
                return Http::response(['access_token' => 'boxnow-token'], 200);
            }

            if ($request->url() === 'https://boxnow.example.test/api/v1/parcels/BOX-123/label.pdf') {
                return Http::response($pdf, 200, ['Content-Type' => 'application/pdf']);
            }

            return Http::response([], 404);
        });

        $label = $service->label($order);

        $this->assertSame($pdf, $label['contents']);
        $this->assertSame('boxnow-BOX-123.pdf', $label['filename']);
        Http::assertSent(function (HttpRequest $request) {
            return $request->url() === 'https://boxnow.example.test/api/v1/parcels/BOX-123/label.pdf'
                && $request->hasHeader('Authorization', 'Bearer boxnow-token')
                && $request->hasHeader('X-PartnerID', 'PARTNER-1')
                && $request->hasHeader('Accept', 'application/pdf');
        });
    }

    public function test_label_rejects_non_pdf_response(): void
    {
        $order = $this->order(['shipping_parcel_id' => 'BOX-123']);
        $service = new BoxNowService(new StubBoxNowSettingsService($this->settings()));

        Http::fakeSequence()
            ->push(['access_token' => 'boxnow-token'], 200)
            ->push('<html>not a pdf</html>', 200);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ispravnu PDF adresnicu');
        $service->label($order);
    }

    public function test_ambiguous_failure_never_reposts_delivery_when_recovery_cannot_confirm_it(): void
    {
        $service = new BoxNowService(new StubBoxNowSettingsService($this->settings()));
        $order = $this->order();
        $deliveryPosts = 0;
        $trackingGets = 0;

        Http::fake(function (HttpRequest $request) use (&$deliveryPosts, &$trackingGets) {
            if ($request->url() === 'https://boxnow.example.test/api/v1/auth-sessions') {
                return Http::response(['access_token' => 'boxnow-token'], 200);
            }

            if ($request->url() === 'https://boxnow.example.test/api/v1/delivery-requests') {
                $deliveryPosts++;

                return Http::response(['message' => 'Gateway timeout'], 504);
            }

            if (str_starts_with($request->url(), 'https://boxnow.example.test/api/v1/parcels')) {
                $trackingGets++;

                return Http::response(['data' => []], 200);
            }

            return Http::response([], 404);
        });

        try {
            $service->createDeliveryRequest($order);
            $this->fail('Ambiguous Box Now result should not be treated as a successful delivery.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('nije potvrdio je li pošiljka kreirana', $exception->getMessage());
        }

        $this->assertSame(1, $deliveryPosts);
        $this->assertSame(3, $trackingGets);
    }

    private function order(array $overrides = []): Order
    {
        $order = new Order();
        $order->forceFill(array_merge([
            'id' => 72,
            'total' => 42.50,
            'payment_code' => 'wspay',
            'payment_phone' => '091 000 0000',
            'payment_email' => 'kupac@example.test',
            'payment_fname' => 'Iva',
            'payment_lname' => 'Ivić',
            'shipping_phone' => '091 000 0000',
            'shipping_email' => 'kupac@example.test',
            'shipping_fname' => 'Iva',
            'shipping_lname' => 'Ivić',
            'comment' => '',
            'commentp' => '10000, Ilica 1_LOCKER-1',
            'tracking_code' => '',
            'shipping_parcel_id' => null,
        ], $overrides));

        $product = new OrderProduct();
        $product->forceFill([
            'product_id' => 9,
            'name' => 'Testna knjiga',
            'total' => 42.50,
        ]);
        $order->setRelation('products', new EloquentCollection([$product]));

        return $order;
    }

    private function settings(array $overrides = []): array
    {
        return array_merge([
            'base_url' => 'https://boxnow.example.test/api/v1',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
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

class StubBoxNowSettingsService extends BoxNowSettingsService
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
