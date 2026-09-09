<?php

namespace Tests\Feature;

use App\Models\Back\Catalog\Product\Product as AdminProduct;
use App\Models\Front\Catalog\Product as FrontProduct;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductLanguageSelectionTest extends TestCase
{
    /** @var string */
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'product_language_testing',
            'database.connections.product_language_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('product_language_testing');

        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sku')->unique();
            $table->string('origin')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('product_language_testing');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    public function test_admin_accepts_and_canonically_serializes_multiple_languages(): void
    {
        $request = new Request([
            'name' => 'Višejezični katalog',
            'sku' => 'KAT-1',
            'price' => 10,
            'quantity' => 1,
            'group' => 'knjige',
            'origin' => ['Njemački', 'Hrvatski', 'Engleski'],
        ]);

        (new AdminProduct())->validateRequest($request);

        $this->assertSame('Hrvatski, Engleski, Njemački', $request->input('origin'));
    }

    public function test_admin_rejects_a_language_outside_the_controlled_list(): void
    {
        $request = new Request([
            'name' => 'Katalog',
            'sku' => 'KAT-2',
            'price' => 10,
            'quantity' => 1,
            'group' => 'knjige',
            'origin' => ['Hrvatski', 'Nepoznati jezik'],
        ]);

        try {
            (new AdminProduct())->validateRequest($request);
            $this->fail('Invalid language selection was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('origin.1', $exception->errors());
        }
    }

    public function test_frontend_language_filter_matches_each_language_in_a_multilingual_product(): void
    {
        DB::table('products')->insert([
            [
                'sku' => 'KAT-3',
                'origin' => 'Hrvatski, Engleski',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sku' => 'KAT-4',
                'origin' => 'Njemački',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $croatian = FrontProduct::query()->whereFacetValues('origin', ['hrvatski'])->pluck('sku')->all();
        $english = FrontProduct::query()->whereFacetValues('origin', ['engleski'])->pluck('sku')->all();

        $this->assertSame(['KAT-3'], $croatian);
        $this->assertSame(['KAT-3'], $english);
    }
}
