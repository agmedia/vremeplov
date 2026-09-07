<?php

namespace Tests\Feature;

use App\Models\Front\Catalog\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductCatalogSortTest extends TestCase
{
    /** @var string */
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'catalog_sort_testing',
            'database.connections.catalog_sort_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('catalog_sort_testing');

        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->decimal('price', 15, 4);
            $table->integer('quantity');
            $table->boolean('status');
            $table->timestamps();
        });

        DB::table('products')->insert([
            [
                'id' => 1,
                'name' => 'Stariji artikl, nedavno uređen',
                'price' => 10,
                'quantity' => 1,
                'status' => 1,
                'created_at' => '2026-08-01 10:00:00',
                'updated_at' => '2026-09-07 10:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Novi artikl',
                'price' => 10,
                'quantity' => 1,
                'status' => 1,
                'created_at' => '2026-09-06 10:00:00',
                'updated_at' => '2026-09-06 10:00:00',
            ],
            [
                'id' => 3,
                'name' => 'Stariji artikl, upravo vraćen na zalihu',
                'price' => 10,
                'quantity' => 1,
                'status' => 1,
                'created_at' => '2026-07-01 10:00:00',
                'updated_at' => '2026-09-07 10:00:00',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('catalog_sort_testing');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    public function test_newest_sort_uses_last_update_and_a_stable_id_tiebreaker(): void
    {
        $ids = (new Product())->filter(new Request(['sort' => 'novi']))->pluck('id')->all();

        $this->assertSame([3, 1, 2], $ids);
    }

    public function test_default_sort_is_also_ordered_by_last_update(): void
    {
        $ids = (new Product())->filter(new Request())->pluck('id')->all();

        $this->assertSame([3, 1, 2], $ids);
    }

    public function test_new_products_widget_scope_uses_last_update(): void
    {
        $ids = (new Product())->newQuery()->available()->last()->pluck('id')->all();

        $this->assertSame([3, 1, 2], $ids);
    }
}
