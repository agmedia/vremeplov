<?php

namespace Tests\Feature;

use App\Helpers\Helper;
use App\Http\Controllers\Api\v2\FilterController;
use App\Http\Controllers\Front\CatalogRouteController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogProductsPayloadTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'catalog_payload_testing',
            'database.connections.catalog_payload_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'settings.images_domain' => 'https://images.example.test/',
            'settings.pagination.front' => 40,
        ]);
        DB::purge('catalog_payload_testing');
        Cache::flush();
        Carbon::setTestNow('2026-10-01 12:00:00');
        $this->createSchema();

        DB::table('settings')->insert([
            'code' => 'currency',
            'key' => 'list',
            'json' => true,
            'value' => json_encode([[
                'code' => 'EUR', 'status' => true, 'main' => true, 'value' => 2,
                'symbol_left' => '', 'symbol_right' => '€', 'decimal_places' => 2,
            ]]),
        ]);
        DB::table('authors')->insert([
            'id' => 1, 'title' => 'Miroslav Krleža', 'slug' => 'miroslav-krleza', 'status' => 1,
        ]);
        DB::table('categories')->insert([
            'id' => 1, 'title' => 'Književnost', 'slug' => 'knjizevnost', 'group' => 'knjige', 'parent_id' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('catalog_payload_testing');
        config(['database.default' => $this->originalConnection]);

        parent::tearDown();
    }

    public function test_catalog_cards_keep_prices_relations_and_pagination_without_per_product_currency_queries(): void
    {
        foreach (range(1, 40) as $id) {
            $this->createProduct($id);
        }
        DB::table('reviews')->insert([
            ['product_id' => 40, 'stars' => 5, 'status' => 1, 'sort_order' => 0],
            ['product_id' => 40, 'stars' => 4, 'status' => 1, 'sort_order' => 1],
            ['product_id' => 40, 'stars' => 1, 'status' => 0, 'sort_order' => 2],
        ]);
        DB::enableQueryLog();

        $payload = $this->products();

        $this->assertSame(40, $payload['total']);
        $this->assertSame(40, $payload['per_page']);
        $this->assertCount(40, $payload['data']);
        $card = $payload['data'][0];
        $this->assertSame(40, $card['id']);
        $this->assertSame('https://images.example.test/catalog/40.webp', $card['image']);
        $this->assertSame('Naslov 40', $card['card_name']);
        $this->assertSame('25.00', $card['main_price']);
        $this->assertSame('25,00€', $card['main_price_text']);
        $this->assertSame('15.00', $card['main_special']);
        $this->assertSame('15,00€', $card['main_special_text']);
        $this->assertSame(2, $card['reviews_count']);
        $this->assertSame(4.5, (float) $card['reviews_avg_stars']);
        $this->assertSame('Miroslav Krleža', $card['author']['title']);
        $this->assertNull($card['action']);
        $this->assertSame('Književnost', $card['card_category']['title']);
        $this->assertStringContainsString('/knjige/knjizevnost', $card['card_category']['url']);
        $this->assertSame('2026-10-02 12:00:00', $card['special_to']);

        foreach (['description', 'meta_description', 'eur_price', 'eur_special', 'secondary_price', 'secondary_special'] as $field) {
            $this->assertArrayNotHasKey($field, $card);
        }
        $settingsQueries = collect(DB::getQueryLog())
            ->filter(fn ($query) => str_contains($query['query'], '"settings"'));
        $this->assertCount(1, $settingsQueries, 'The whole page shares the existing main currency cache.');
    }

    public function test_coupon_changes_are_applied_immediately_without_caching_customer_prices(): void
    {
        DB::table('product_actions')->insert(['id' => 1, 'coupon' => 'SAVE', 'status' => 1]);
        $this->createProduct(1, ['action_id' => 1]);

        $this->assertSame('25.00', $this->products()['data'][0]['main_special']);

        session()->put(config('session.cart') . '_coupon', 'SAVE');
        $card = $this->products()['data'][0];
        $this->assertSame('15.00', $card['main_special']);
        $this->assertSame('SAVE', $card['action']['coupon']);

        session()->forget(config('session.cart') . '_coupon');
        $this->assertSame('25.00', $this->products()['data'][0]['main_special']);
    }

    public function test_server_rendered_catalog_exposes_the_same_lean_card_payload_for_frontend_hydration(): void
    {
        $this->createProduct(1);
        DB::table('reviews')->insert([
            'product_id' => 1, 'stars' => 5, 'status' => 1, 'sort_order' => 0,
        ]);

        $method = new \ReflectionMethod(CatalogRouteController::class, 'catalogProducts');
        $method->setAccessible(true);
        $products = $method->invoke(
            app(CatalogRouteController::class),
            Request::create('/knjige', 'GET'),
            'knjige'
        );
        $payload = $products->toArray();

        $this->assertSame(1, $payload['total']);
        $this->assertSame(1, $payload['data'][0]['id']);
        $this->assertSame('Naslov 1', $payload['data'][0]['card_name']);
        $this->assertSame('Književnost', $payload['data'][0]['card_category']['title']);
        $this->assertSame(1, $payload['data'][0]['reviews_count']);
        $this->assertArrayNotHasKey('description', $payload['data'][0]);
        $this->assertArrayNotHasKey('meta_description', $payload['data'][0]);
        $this->assertArrayNotHasKey('secondary_price', $payload['data'][0]);
        $this->assertArrayNotHasKey('categories', $payload['data'][0]);
        $this->assertArrayNotHasKey('author', $payload['data'][0]);
        $this->assertArrayNotHasKey('action', $payload['data'][0]);
    }

    public function test_expired_discounts_and_inventory_changes_are_visible_on_the_next_request(): void
    {
        $this->createProduct(1, ['special_to' => '2026-10-01 12:00:01']);
        $this->assertSame('15.00', $this->products()['data'][0]['main_special']);

        Carbon::setTestNow('2026-10-01 12:00:02');
        $this->assertSame('25.00', $this->products()['data'][0]['main_special']);

        DB::table('products')->where('id', 1)->update(['price' => 16]);
        $this->assertSame('32.00', $this->products()['data'][0]['main_price']);

        DB::table('products')->where('id', 1)->update(['quantity' => 0]);
        $this->assertSame(0, $this->products()['total']);
    }

    /** @dataProvider authorSearchTerms */
    public function test_author_search_preserves_results_while_loading_only_product_identifiers(string $term): void
    {
        DB::table('authors')->where('id', 1)->update(['title' => 'Miroslav Gustav Krleža']);
        $this->createProduct(1);
        $this->createProduct(2, ['quantity' => 0]);
        $this->createProduct(3, ['status' => 0]);
        $this->createProduct(4, ['price' => 0]);
        $this->createProduct(5, ['author_id' => null, 'name' => $term]);
        DB::enableQueryLog();

        $ids = json_decode(Helper::search($term), true);

        $this->assertEqualsCanonicalizing([1, 5], $ids);
        $loadedAuthorProducts = collect(DB::getQueryLog())
            ->first(fn ($query) => str_contains($query['query'], '"products"."author_id" in'));
        $this->assertNotNull($loadedAuthorProducts);
        $this->assertStringStartsWith('select "id", "author_id" from "products"', $loadedAuthorProducts['query']);
    }

    public static function authorSearchTerms(): array
    {
        return [['Krleža'], ['Miroslav Krleža'], ['Miroslav Gustav Krleža']];
    }

    private function products(): array
    {
        return app(FilterController::class)->products(new Request([
            'params' => ['group' => 'knjige'],
        ]))->getData(true);
    }

    private function createProduct(int $id, array $overrides = []): void
    {
        DB::table('products')->insert(array_replace([
            'id' => $id, 'name' => 'NASLOV ' . $id, 'sku' => 'SKU-' . $id,
            'slug' => 'naslov-' . $id, 'url' => 'knjige/naslov-' . $id,
            'image' => 'catalog/' . $id . '.jpg', 'group' => 'knjige',
            'price' => 12.5, 'special' => 7.5,
            'special_from' => '2026-09-30 12:00:00', 'special_to' => '2026-10-02 12:00:00',
            'quantity' => 1, 'status' => 1, 'author_id' => 1, 'publisher_id' => null, 'action_id' => null,
            'description' => str_repeat('Long product description. ', 400),
            'meta_description' => 'Unused listing metadata.',
            'updated_at' => '2026-10-01 12:00:00',
        ], $overrides));
        DB::table('product_category')->insert(['product_id' => $id, 'category_id' => 1]);
    }

    private function createSchema(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code');
            $table->string('key');
            $table->text('value');
            $table->boolean('json');
        });
        Schema::create('authors', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->string('slug');
            $table->boolean('status');
        });
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            foreach (['name', 'sku', 'slug', 'url', 'image', 'group'] as $column) {
                $table->string($column);
            }
            foreach (['author_id', 'publisher_id', 'action_id'] as $column) {
                $table->unsignedInteger($column)->nullable();
            }
            $table->decimal('price', 15, 4);
            $table->decimal('special', 15, 4)->nullable();
            $table->dateTime('special_from')->nullable();
            $table->dateTime('special_to')->nullable();
            $table->integer('quantity');
            $table->boolean('status');
            $table->text('description');
            $table->text('meta_description');
            $table->timestamps();
        });
        Schema::create('product_actions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('coupon')->nullable();
            $table->boolean('status');
        });
        Schema::create('categories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('parent_id');
            $table->string('title');
            $table->string('slug');
            $table->string('group');
        });
        Schema::create('product_category', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('category_id');
        });
        Schema::create('reviews', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('stars');
            $table->boolean('status');
            $table->integer('sort_order');
        });
    }
}
