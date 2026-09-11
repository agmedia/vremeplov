<?php

namespace Tests\Feature;

use App\Helpers\RouteResolver;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class CatalogRouteResolverTest extends TestCase
{
    use RefreshDatabase;

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

    /** @test */
    public function it_rejects_a_product_on_an_unrelated_path_instead_of_leaving_string_breadcrumbs()
    {
        $slug = 'malformed-route-' . Str::random(12);
        $product = Product::create([
            'name' => 'Malformed route product',
            'slug' => $slug,
            'url' => 'knjige/ispravna-kategorija/' . $slug,
            'group' => 'knjige',
            'price' => 10,
            'quantity' => 1,
            'status' => 1,
        ]);
        $resolver = new RouteResolver(
            Request::create('/plakati/pogresna-kategorija/pogresan-zavrsetak'),
            'plakati',
            'pogresna-kategorija',
            'pogresan-zavrsetak',
            $product
        );

        $this->expectException(NotFoundHttpException::class);

        $resolver->setRoute();
    }

    /** @test */
    public function it_replaces_legacy_category_seo_with_a_clean_title_and_short_description()
    {
        $category = Category::create([
            'parent_id' => 0,
            'title' => 'Arheologija',
            'group' => 'knjige',
            'slug' => 'arheologija-test',
            'meta_title' => 'ARHEOLOGIJA-Antikvarijat Vremeplov',
            'meta_description' => 'ARHEOLOGIJA-Antikvarijat Vremeplov: Lopašićeva 11, Zagreb. broj telefona: 01/ 777 - 3261. Dodaj u košaricu.',
            'status' => 1,
        ]);

        $resolver = new RouteResolver(
            Request::create('/knjige/' . $category->slug),
            'knjige',
            $category->slug
        );
        $resolver->setRoute();

        $this->assertSame('Arheologija', $resolver->title);
        $this->assertSame(
            'Knjige iz kategorije Arheologija: rabljena, rijetka i antikvarna izdanja.',
            $resolver->description
        );
        $this->assertStringNotContainsString('Lopašićeva', $resolver->description);
    }

    /** @test */
    public function it_keeps_a_meaningful_curated_category_description()
    {
        $category = Category::create([
            'parent_id' => 0,
            'title' => 'Filmski plakati',
            'group' => 'plakati',
            'slug' => 'filmski-plakati-test',
            'meta_title' => 'Stari filmski plakati',
            'meta_description' => 'Otkrijte originalne filmske plakate, rijetke primjerke i vizualnu povijest domaće i svjetske kinematografije.',
            'status' => 1,
        ]);

        $resolver = new RouteResolver(
            Request::create('/plakati/' . $category->slug),
            'plakati',
            $category->slug
        );
        $resolver->setRoute();

        $this->assertSame('Filmski plakati', $resolver->title);
        $this->assertSame($category->meta_description, $resolver->description);
    }

    /** @test */
    public function it_uses_a_valid_category_description_when_the_meta_description_is_legacy_noise()
    {
        $category = Category::create([
            'parent_id' => 0,
            'title' => 'Arhitektura',
            'group' => 'knjige',
            'slug' => 'arhitektura-opis-test',
            'meta_description' => 'ARHITEKTURA-Antikvarijat Vremeplov: Lopašićeva 11. Dodaj u košaricu.',
            'description' => 'Knjige o povijesti arhitekture, graditeljstvu, projektiranju i istaknutim svjetskim arhitektima.',
            'status' => 1,
        ]);

        $resolver = new RouteResolver(
            Request::create('/knjige/' . $category->slug),
            'knjige',
            $category->slug
        );
        $resolver->setRoute();

        $this->assertSame($category->description, $resolver->description);
    }

    /** @test */
    public function it_uses_curated_editorial_content_when_a_main_category_only_has_legacy_text()
    {
        $slug = 'urednicka-kategorija-' . Str::random(10);
        config([
            'seo.category_content.categories.knjige/' . $slug => [
                'description' => 'Kratak i koristan opis glavne kategorije za rezultate pretraživanja.',
                'paragraphs' => [
                    'Prvi urednički odlomak o stvarnoj ponudi kategorije.',
                    'Drugi urednički odlomak pomaže kupcu pri odabiru primjerka.',
                ],
            ],
        ]);
        $category = Category::create([
            'parent_id' => 0,
            'title' => 'Urednička kategorija',
            'group' => 'knjige',
            'slug' => $slug,
            'description' => 'UREĐNIČKA KATEGORIJA-Antikvarijat Vremeplov. Dodaj u košaricu.',
            'status' => 1,
        ]);
        $resolver = new RouteResolver(
            Request::create('/knjige/' . $category->slug),
            'knjige',
            $category->slug
        );

        $resolver->setRoute();
        $meta = $resolver->setMeta();

        $this->assertSame(
            'Kratak i koristan opis glavne kategorije za rezultate pretraživanja.',
            $meta['description']
        );
        $this->assertCount(2, $meta['content']);
        $this->assertFalse($meta['show_category_description']);
    }
}
