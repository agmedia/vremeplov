<?php

namespace Tests\Unit;

use App\Models\Front\Catalog\Product;
use PHPUnit\Framework\TestCase;

class ProductCardNameTest extends TestCase
{
    public function test_it_normalizes_legacy_titles_that_are_predominantly_uppercase(): void
    {
        $uppercase = new Product();
        $uppercase->setRawAttributes(['name' => 'TOMISLAV LADAN : ETYMOLOGICON : TUMAČ RAZNOVRSNIH POJMOVA']);

        $formatted = new Product();
        $formatted->setRawAttributes(['name' => 'J. K. Rowling: Harry Potter i kamen mudraca']);

        $this->assertSame(
            'Tomislav Ladan: Etymologicon: Tumač Raznovrsnih Pojmova',
            $uppercase->card_name
        );
        $this->assertSame('J. K. Rowling: Harry Potter i kamen mudraca', $formatted->card_name);

        $mixedLegacy = new Product();
        $mixedLegacy->setRawAttributes([
            'name' => 'DISNEY MAGIC ENGLISH : FRIENDS-PRIJATELJI : NAUČITE ENGLESKI : S DVD-om',
        ]);

        $this->assertSame(
            'Disney Magic English: Friends-Prijatelji: Naučite Engleski: s DVD-om',
            $mixedLegacy->card_name
        );
    }

    public function test_it_keeps_common_acronyms_and_roman_numerals_uppercase(): void
    {
        $product = new Product();
        $product->setRawAttributes(['name' => 'HNK U DVD IZDANJU, SVEZAK XX. - POVIJEST BIH']);

        $this->assertSame('HNK u DVD Izdanju, Svezak XX. - Povijest BiH', $product->card_name);

        $hyphenated = new Product();
        $hyphenated->setRawAttributes(['name' => 'PRIRUČNIK NA DVD-OM, IZDANJE VBZ']);

        $this->assertSame('Priručnik na DVD-om, Izdanje VBZ', $hyphenated->card_name);
    }
}
