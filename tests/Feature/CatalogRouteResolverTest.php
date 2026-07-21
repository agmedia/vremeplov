<?php

namespace Tests\Feature;

use App\Helpers\RouteResolver;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogRouteResolverTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_resolves_an_old_product_url_after_its_category_slug_changes()
    {
        $slug = 'route-resolver-test-' . Str::random(12);
        $product = Product::create([
            'name' => 'Route resolver test product',
            'slug' => $slug,
            'url' => 'plakati/novi-naziv-kategorije/' . $slug,
            'price' => 10,
            'quantity' => 1,
            'status' => 1,
        ]);
        $category = Category::create([
            'parent_id' => 0,
            'title' => 'Novi naziv kategorije',
            'group' => 'plakati',
            'slug' => 'novi-naziv-kategorije',
            'status' => 1,
        ]);

        DB::table('product_category')->insert([
            'product_id' => $product->id,
            'category_id' => $category->id,
        ]);

        $request = Request::create('/' . $product->url);
        $resolver = new RouteResolver(
            $request,
            'plakati',
            'stari-naziv-kategorije',
            $slug
        );

        $resolver->setRoute();

        $this->assertTrue($resolver->product->is($product));
        $this->assertTrue($resolver->category->is($category));
        $this->assertNull($resolver->subcategory);
    }
}
