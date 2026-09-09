<?php

namespace Tests\Feature;

use App\Http\Controllers\Front\CatalogRouteController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogSuggestCardNameTest extends TestCase
{
    /** @var string */
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'catalog_suggest_testing',
            'database.connections.catalog_suggest_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'settings.images_domain' => 'https://images.example.test/',
        ]);
        DB::purge('catalog_suggest_testing');

        Schema::create('authors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('slug');
            $table->string('url')->nullable();
            $table->boolean('status')->default(true);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('url');
            $table->string('image')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 15, 4)->default(0);
            $table->decimal('special', 15, 4)->nullable();
            $table->dateTime('special_from')->nullable();
            $table->dateTime('special_to')->nullable();
            $table->unsignedInteger('viewed')->default(0);
            $table->boolean('status')->default(true);
        });
    }

    protected function tearDown(): void
    {
        DB::purge('catalog_suggest_testing');
        config(['database.default' => $this->originalConnection]);

        parent::tearDown();
    }

    public function test_product_suggestions_include_the_customer_facing_card_name(): void
    {
        $authorId = DB::table('authors')->insertGetId([
            'title' => 'Miroslav Krleža',
            'slug' => 'miroslav-krleza',
            'url' => 'autori/miroslav-krleza',
            'status' => 1,
        ]);

        DB::table('products')->insert([
            'author_id' => $authorId,
            'name' => 'KRLEŽA MIROSLAV : PANORAMA POGLEDA',
            'sku' => 'TEST-1',
            'url' => 'knjige/panorama-pogleda',
            'quantity' => 1,
            'price' => 39.82,
            'viewed' => 10,
            'status' => 1,
        ]);

        $response = app(CatalogRouteController::class)->suggest(
            Request::create('/pretrazi/suggest', 'GET', ['q' => 'PANORAMA'])
        );
        $product = $response->getData(true)['products'][0];

        $this->assertSame('KRLEŽA MIROSLAV : PANORAMA POGLEDA', $product['name']);
        $this->assertSame('Krleža Miroslav: Panorama Pogleda', $product['card_name']);
    }
}
