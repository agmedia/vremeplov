<?php

namespace Tests\Unit;

use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Product;
use App\Models\Seo;
use Tests\TestCase;

class SeoMetadataTest extends TestCase
{
    /** @test */
    public function it_preserves_spacing_when_normalizing_rich_text_product_descriptions(): void
    {
        $product = new Product([
            'name' => 'Miroslav Krleža: Hrvatski bog Mars',
            'meta_title' => '',
            'meta_description' => '<p>Uvez: tvrdi</p><p>Broj stranica: 327<br>Jezik: hrvatski</p>',
        ]);
        $product->setRelation('author', new Author(['title' => 'Krleža Miroslav']));

        $metadata = Seo::getProductData($product);

        $this->assertSame(
            'Uvez: tvrdi Broj stranica: 327 Jezik: hrvatski',
            $metadata['description']
        );
    }

    /** @test */
    public function it_decodes_entities_and_collapses_whitespace_in_product_descriptions(): void
    {
        $product = new Product([
            'name' => 'Testna knjiga',
            'meta_title' => '',
            'meta_description' => "<div>Stare&nbsp;knjige</div>\n\t <div>u Zagrebu</div>",
        ]);

        $metadata = Seo::getProductData($product);

        $this->assertSame('Stare knjige u Zagrebu', $metadata['description']);
    }
}
