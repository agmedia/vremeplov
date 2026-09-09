<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\v2\FilterController;
use App\Models\Front\Catalog\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogCharacteristicFacetTest extends TestCase
{
    /** @var string */
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'catalog_facet_testing',
            'database.connections.catalog_facet_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('catalog_facet_testing');
        Cache::flush();

        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('author_id')->default(0);
            $table->unsignedBigInteger('publisher_id')->default(0);
            $table->string('group');
            $table->string('year', 4)->nullable();
            $table->string('letter')->nullable();
            $table->string('condition')->nullable();
            $table->string('binding')->nullable();
            $table->string('origin')->nullable();
            $table->decimal('price', 15, 4);
            $table->integer('quantity');
            $table->boolean('status');
            $table->timestamps();
        });

        DB::table('products')->insert([
            $this->product(1, 'knjige', 'Latinica', 'Vrlo dobro', 'Tvrdi', 'Hrvatski'),
            $this->product(2, 'knjige', 'Latinica', 'Dobro', 'Meki', 'Engleski'),
            $this->product(3, 'knjige', 'Ćirilica', 'Vrlo dobro', 'Tvrdi', 'Srpski'),
            $this->product(4, 'ostalo', 'Latinica', 'Korišteno', 'Tvrdi', 'Hrvatski'),
            $this->product(5, 'query-count', 'Latinica', 'Dobro', 'Meki', 'Hrvatski'),
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('catalog_facet_testing');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    public function test_other_facets_are_narrowed_with_and_logic(): void
    {
        $facets = $this->facets([
            'group' => 'knjige',
            'pismo' => 'latinica',
            'stanje' => 'vrlo dobro',
        ]);

        $this->assertSame(['tvrdi' => 1], $this->counts($facets, 'uvez'));
        $this->assertSame(['hrvatski' => 1], $this->counts($facets, 'jezik'));

        // The selected facet itself remains disjunctive so another value can
        // be added without weakening the AND relationship between facets.
        $this->assertSame(
            ['cirilica' => 1, 'latinica' => 1],
            $this->counts($facets, 'pismo')
        );
        $this->assertSame(
            ['dobro' => 1, 'vrlo dobro' => 1],
            $this->counts($facets, 'stanje')
        );
    }

    public function test_unavailable_options_disappear_but_selected_zero_result_values_remain_removable(): void
    {
        $facets = $this->facets([
            'group' => 'knjige',
            'pismo' => 'latinica',
            'jezik' => 'srpski',
        ]);

        $this->assertSame(
            ['cirilica' => 1, 'latinica' => 0],
            $this->counts($facets, 'pismo')
        );
        $this->assertSame(
            ['engleski' => 1, 'hrvatski' => 1, 'srpski' => 0],
            $this->counts($facets, 'jezik')
        );
        $this->assertArrayNotHasKey('stanje', $facets);
        $this->assertArrayNotHasKey('uvez', $facets);
    }

    public function test_all_characteristic_counts_are_loaded_with_one_database_query(): void
    {
        DB::enableQueryLog();

        $this->facets(['group' => 'query-count']);

        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_product_results_also_combine_different_facets_with_and_logic(): void
    {
        $ids = (new Product())->filter(new Request([
            'group' => 'knjige',
            'pismo' => ['latinica'],
            'stanje' => ['vrlo dobro'],
        ]))->pluck('id')->all();

        $this->assertSame([1], $ids);
    }

    private function facets(array $params): array
    {
        $response = app(FilterController::class)->characteristics(new Request([
            'params' => $params,
        ]));

        return collect($response->getData(true))
            ->keyBy('key')
            ->all();
    }

    private function counts(array $facets, string $key): array
    {
        return collect($facets[$key]['items'])
            ->pluck('count', 'value')
            ->sortKeys()
            ->all();
    }

    private function product(
        int $id,
        string $group,
        string $letter,
        string $condition,
        string $binding,
        string $origin
    ): array {
        return [
            'id' => $id,
            'author_id' => 0,
            'publisher_id' => 0,
            'group' => $group,
            'year' => '2020',
            'letter' => $letter,
            'condition' => $condition,
            'binding' => $binding,
            'origin' => $origin,
            'price' => 10,
            'quantity' => 1,
            'status' => 1,
            'created_at' => '2026-09-09 12:00:00',
            'updated_at' => '2026-09-09 12:00:00',
        ];
    }
}
