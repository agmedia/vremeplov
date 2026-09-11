<?php

namespace Tests\Unit;

use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\ProductImage;
use App\Models\Front\Catalog\Publisher;
use App\Services\GoogleMerchantFeed;
use Illuminate\Support\Collection;
use Tests\TestCase;
use XMLWriter;

class GoogleMerchantFeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://www.antikvarijat-vremeplov.hr',
            'settings.images_domain' => 'https://img.example.test/',
            'settings.unknown_author' => 1,
            'settings.unknown_publisher' => 1,
        ]);
    }

    /** @test */
    public function it_builds_google_fields_for_the_exact_used_copy(): void
    {
        $product = $this->product([
            'isbn' => '978-953-00000-0-1',
            'special' => 12,
            'special_from' => now()->subDay(),
            'special_to' => now()->addDay(),
            'condition' => 'vrlo dobro',
            'note' => 'posveta na predlistu',
        ]);
        $product->setRelation('author', tap(new Author(['title' => 'Ana Autorica']), fn ($author) => $author->id = 2));
        $product->setRelation('publisher', tap(new Publisher(['title' => 'Nakladnik d.o.o.']), fn ($publisher) => $publisher->id = 2));
        $product->setRelation('categories', new Collection([
            tap(new Category(['title' => 'Književnost', 'parent_id' => 0]), fn ($category) => $category->id = 10),
            tap(new Category(['title' => 'Romani', 'parent_id' => 10]), fn ($category) => $category->id = 11),
        ]));
        $product->setRelation('images', new Collection([
            new ProductImage(['image' => 'media/products/321-detail.jpg']),
        ]));
        $product->setRelation('action', null);

        $data = (new GoogleMerchantFeed())->itemData($product);

        $this->assertSame('vremeplov-321', $data['id']);
        $this->assertSame('15.00 EUR', $data['price']);
        $this->assertSame('12.00 EUR', $data['sale_price']);
        $this->assertSame('used', $data['condition']);
        $this->assertSame('in_stock', $data['availability']);
        $this->assertSame('9789530000001', $data['gtin']);
        $this->assertSame('yes', $data['identifier_exists']);
        $this->assertSame('Nakladnik d.o.o.', $data['brand']);
        $this->assertSame('Knjige > Književnost > Romani', $data['product_type']);
        $this->assertStringContainsString('Stanje: vrlo dobro', $data['description']);
        $this->assertStringContainsString('Napomena: posveta na predlistu', $data['description']);
        $this->assertStringContainsString('Šifra primjerka: AB-321', $data['description']);
        $this->assertSame(['https://img.example.test/media/products/321-detail.webp'], $data['additional_image_link']);
    }

    /** @test */
    public function it_does_not_invent_an_identifier_when_an_isbn_is_invalid(): void
    {
        $product = $this->product(['isbn' => 'nepoznat']);
        $product->setRelation('author', null);
        $product->setRelation('publisher', null);
        $product->setRelation('categories', new Collection());
        $product->setRelation('images', new Collection());
        $product->setRelation('action', null);

        $data = (new GoogleMerchantFeed())->itemData($product);

        $this->assertSame('no', $data['identifier_exists']);
        $this->assertArrayNotHasKey('gtin', $data);
        $this->assertArrayNotHasKey('brand', $data);
    }

    /** @test */
    public function it_writes_valid_namespaced_xml_and_escapes_product_text(): void
    {
        $product = $this->product(['name' => 'Knjiga & grad']);
        $product->setRelation('author', null);
        $product->setRelation('publisher', null);
        $product->setRelation('categories', new Collection());
        $product->setRelation('images', new Collection());
        $product->setRelation('action', null);
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('rss');
        $writer->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');

        (new GoogleMerchantFeed())->writeItem($writer, $product);

        $writer->endElement();
        $writer->endDocument();
        $xml = $writer->outputMemory();

        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringContainsString('<g:title>Knjiga &amp; grad</g:title>', $xml);
        $this->assertStringContainsString('<g:price>15.00 EUR</g:price>', $xml);
    }

    private function product(array $attributes = []): Product
    {
        $product = new Product(array_merge([
            'name' => 'Testna knjiga',
            'group' => 'knjige',
            'url' => 'knjige/knjizevnost/testna-knjiga-321',
            'image' => 'media/products/321.jpg',
            'price' => 15,
            'sku' => 'AB-321',
            'special' => null,
            'special_from' => null,
            'special_to' => null,
            'quantity' => 1,
            'status' => 1,
            'author_id' => 2,
            'publisher_id' => 2,
        ], $attributes));
        $product->id = 321;

        return $product;
    }
}
