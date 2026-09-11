<?php

namespace Tests\Unit;

use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\Publisher;
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

    /** @test */
    public function it_separates_labels_in_already_flattened_product_descriptions(): void
    {
        $product = new Product([
            'name' => 'Miroslav Krleža: Hrvatski bog Mars',
            'meta_title' => '',
            'meta_description' => 'Uvez : tvrdiBroj stranica : 327Jezik : hrvatskiPismo : latinicaGodina : 2008.',
        ]);

        $metadata = Seo::getProductData($product);

        $this->assertSame(
            'Uvez : tvrdi Broj stranica : 327 Jezik : hrvatski Pismo : latinica Godina : 2008.',
            $metadata['description']
        );
    }

    /** @test */
    public function it_uses_the_product_description_when_a_custom_meta_description_is_missing(): void
    {
        $product = new Product([
            'name' => 'Mudrost menopauze',
            'meta_title' => '',
            'meta_description' => '',
            'description' => '<p>Stvaranje tjelesnog i emocionalnog zdravlja.</p><p>Holistički pristup promjenama.</p>',
        ]);

        $metadata = Seo::getProductData($product);

        $this->assertSame(
            'Stvaranje tjelesnog i emocionalnog zdravlja. Holistički pristup promjenama.',
            $metadata['description']
        );
    }

    /** @test */
    public function it_distinguishes_two_physical_copies_with_their_condition_and_note(): void
    {
        $first = new Product([
            'name' => 'Isti naslov',
            'condition' => 'vrlo dobro',
            'note' => 'posveta na predlistu',
            'year' => '1984',
            'binding' => 'tvrdi',
        ]);
        $second = new Product([
            'name' => 'Isti naslov',
            'condition' => 'dobro',
            'note' => 'bez potpisa i podcrtavanja',
            'year' => '1984',
            'binding' => 'tvrdi',
        ]);
        $author = new Author(['title' => 'Isti autor']);
        $publisher = new Publisher(['title' => 'Isti nakladnik']);
        $first->setRelation('author', $author)->setRelation('publisher', $publisher);
        $second->setRelation('author', $author)->setRelation('publisher', $publisher);

        $firstDescription = Seo::getProductData($first)['description'];
        $secondDescription = Seo::getProductData($second)['description'];

        $this->assertNotSame($firstDescription, $secondDescription);
        $this->assertStringContainsString('Stanje: vrlo dobro', $firstDescription);
        $this->assertStringContainsString('posveta na predlistu', $firstDescription);
        $this->assertStringContainsString('Stanje: dobro', $secondDescription);
        $this->assertStringContainsString('bez potpisa i podcrtavanja', $secondDescription);
    }

    /** @test */
    public function it_keeps_copy_condition_in_a_long_generated_search_snippet(): void
    {
        $product = new Product([
            'name' => 'Dugački opis primjerka',
            'description' => str_repeat('Detaljan sadržaj knjige i njezina povijesnog konteksta. ', 8),
            'condition' => 'vrlo dobro',
            'note' => 'mala oznaka vlasnika',
        ]);

        $description = Seo::getProductData($product)['description'];

        $this->assertLessThanOrEqual(160, mb_strlen($description, 'UTF-8'));
        $this->assertStringContainsString('Stanje: vrlo dobro', $description);
        $this->assertDoesNotMatchRegularExpression('/\s\p{L}{1,3}…$/u', $description);
    }
}
