<?php

namespace Tests\Unit;

use App\Models\Back\Catalog\Product\Product;
use Tests\TestCase;

class ProductImageUrlTest extends TestCase
{
    public function test_admin_lightbox_image_url_uses_the_original_catalog_image(): void
    {
        config([
            'settings.images_domain' => 'https://images.example.test/',
        ]);

        $product = new Product([
            'image' => 'media/img/products/42/book-cover.jpg',
        ]);

        $this->assertSame(
            'https://images.example.test/media/img/products/42/book-cover.jpg',
            $product->image_url
        );
    }

    public function test_admin_lightbox_preserves_an_absolute_image_url(): void
    {
        $product = new Product([
            'image' => 'https://cdn.example.test/books/book-cover.webp',
        ]);

        $this->assertSame(
            'https://cdn.example.test/books/book-cover.webp',
            $product->image_url
        );
    }
}
