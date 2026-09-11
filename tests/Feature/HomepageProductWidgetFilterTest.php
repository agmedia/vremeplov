<?php

namespace Tests\Feature;

use App\Helpers\Helper;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomepageProductWidgetFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['settings.images_domain' => 'https://cdn.example.test/']);

        // The imported application schema contains this legacy column, while
        // the original create-products migration predates it.
        if ( ! Schema::hasColumn('products', 'group')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('group')->default('knjige');
            });
        }
    }

    public function test_popular_homepage_widget_with_books_catalog_group_excludes_other_item_groups(): void
    {
        $groupId = DB::table('widget_groups')->insertGetId([
            'template' => 'product_carousel',
            'title' => 'Najpopularnije',
            'slug' => 'najpopularnije-knjige',
            'width' => 12,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('widgets')->insert([
            'group_id' => $groupId,
            'title' => 'Najpopularnije',
            'subtitle' => 'Najpopularnije knjige.',
            'data' => serialize([
                'target' => 'product',
                'popular' => 'on',
                'catalog_group' => 'knjige',
            ]),
            'url' => '/knjige',
            'width' => 12,
            'sort_order' => 1,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createProduct('Popularna knjiga', 'knjige', 50);
        $this->createProduct('Druga popularna knjiga', 'knjige', 25);
        $this->createProduct('Zemljopisna karta', 'karte-i-mape', 1000);
        $this->createProduct('Stara razglednica', 'razglednice', 900);
        $this->createProduct('Kolekcionarski predmet', 'ostalo', 800);

        $rendered = Helper::setDescription('++najpopularnije-knjige++');

        $this->assertStringContainsString('Popularna knjiga', $rendered);
        $this->assertStringContainsString('Druga popularna knjiga', $rendered);
        $this->assertStringNotContainsString('Zemljopisna karta', $rendered);
        $this->assertStringNotContainsString('Stara razglednica', $rendered);
        $this->assertStringNotContainsString('Kolekcionarski predmet', $rendered);
    }

    public function test_homepage_caps_each_product_carousel_and_skips_blog_only_scripts(): void
    {
        config(['seo.homepage_product_limit' => 3]);
        $groupId = DB::table('widget_groups')->insertGetId([
            'template' => 'product_carousel',
            'title' => 'Brza naslovnica',
            'slug' => 'brza-naslovnica',
            'width' => 12,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('widgets')->insert([
            'group_id' => $groupId,
            'title' => 'Brza naslovnica',
            'subtitle' => 'Ograničen broj kartica.',
            'data' => serialize([
                'target' => 'product',
                'new' => 'on',
                'catalog_group' => 'knjige',
            ]),
            'url' => '/knjige',
            'width' => 12,
            'sort_order' => 1,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('pages')->insert([
            'title' => 'Naslovnica',
            'slug' => 'homepage',
            'description' => '++brza-naslovnica++',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (range(1, 6) as $number) {
            $this->createProduct('Naslov ' . $number, 'knjige', $number);
        }

        $response = $this->get('/');

        $response->assertOk();
        $this->assertSame(3, substr_count($response->getContent(), 'product-card product-card--refined'));
        $response->assertDontSee('/js/imagesloaded/imagesloaded.pkgd.min.js', false);
        $response->assertDontSee('/js/shufflejs/dist/shuffle.min.js', false);
    }

    private function createProduct(string $name, string $group, int $viewed): void
    {
        DB::table('products')->insert([
            'name' => $name,
            'sku' => 'SKU-' . $viewed,
            'group' => $group,
            'slug' => str_replace(' ', '-', strtolower($name)),
            'url' => '/' . $group . '/' . str_replace(' ', '-', strtolower($name)),
            'image' => 'catalog/' . $viewed . '.jpg',
            'price' => 10,
            'quantity' => 1,
            'viewed' => $viewed,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
