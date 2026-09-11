<?php

namespace App\Services;

use App\Models\Front\Catalog\Product;
use App\Models\Seo;
use Carbon\Carbon;
use Illuminate\Support\Str;
use XMLWriter;

class GoogleMerchantFeed
{
    private const GOOGLE_NAMESPACE = 'http://base.google.com/ns/1.0';

    /**
     * Stream the complete active catalog without holding 30,000 products in memory.
     */
    public function stream(): void
    {
        $writer = new XMLWriter();
        $writer->openUri('php://output');
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('rss');
        $writer->writeAttribute('xmlns:g', self::GOOGLE_NAMESPACE);
        $writer->writeAttribute('version', '2.0');
        $writer->startElement('channel');
        $writer->writeElement('title', 'Antikvarijat Vremeplov');
        $writer->writeElement('link', url('/'));
        $writer->writeElement('description', 'Aktivni artikli Antikvarijata Vremeplov dostupni za online kupnju.');

        Product::query()
            ->active()
            ->hasStock()
            ->where('price', '>', 0)
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->with(['author', 'publisher', 'categories', 'images', 'action'])
            ->orderBy('products.id')
            ->chunkById(250, function ($products) use ($writer): void {
                foreach ($products as $product) {
                    $this->writeItem($writer, $product);
                }

                $writer->flush();
            }, 'products.id', 'id');

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();
        $writer->flush();
    }


    /**
     * Exposed separately so feed fields can be regression-tested per product.
     */
    public function itemData(Product $product): array
    {
        $isbn = $product->group === 'knjige' ? $this->isbn13($product->isbn) : null;
        $publisher = isset($product->publisher->title)
            && (int) $product->publisher_id !== (int) config('settings.unknown_publisher')
                ? Seo::normalizeDescription($product->publisher->title)
                : '';
        $author = isset($product->author->title)
            && (int) $product->author_id !== (int) config('settings.unknown_author')
                ? Seo::normalizeDescription($product->author->title)
                : '';
        $title = Seo::normalizeDescription($product->card_name ?: $product->name);

        if ($author !== '' && mb_stripos($title, $author, 0, 'UTF-8') === false) {
            $title .= ' — ' . $author;
        }

        $data = [
            'id' => 'vremeplov-' . $product->id,
            'title' => Seo::limitDescription($title, 150),
            'description' => Seo::productDescription($product, 5000),
            'link' => url('/' . ltrim((string) $product->url, '/')),
            'image_link' => $this->absoluteImageUrl($product->image),
            'additional_image_link' => $product->relationLoaded('images')
                ? $product->images
                    ->map(fn ($image): string => $this->absoluteImageUrl($image->image))
                    ->filter()
                    ->unique()
                    ->reject(fn (string $image): bool => $image === $this->absoluteImageUrl($product->image))
                    ->take(10)
                    ->values()
                    ->all()
                : [],
            'availability' => 'in_stock',
            'condition' => 'used',
            'price' => number_format((float) $product->price, 2, '.', '') . ' EUR',
            'product_type' => $this->productType($product),
            'identifier_exists' => $isbn ? 'yes' : 'no',
        ];

        $salePrice = $this->currentPublicSalePrice($product);

        if ($salePrice !== null) {
            $data['sale_price'] = number_format($salePrice, 2, '.', '') . ' EUR';
        }

        if ($isbn) {
            $data['gtin'] = $isbn;

            if ($publisher !== '') {
                $data['brand'] = $publisher;
            }
        }

        return $data;
    }


    public function writeItem(XMLWriter $writer, Product $product): void
    {
        $data = $this->itemData($product);
        $writer->startElement('item');

        foreach (['id', 'title', 'description', 'link', 'image_link'] as $attribute) {
            $this->writeGoogleElement($writer, $attribute, $data[$attribute]);
        }

        foreach ($data['additional_image_link'] as $image) {
            $this->writeGoogleElement($writer, 'additional_image_link', $image);
        }

        foreach (['availability', 'condition', 'price', 'sale_price', 'product_type', 'identifier_exists', 'gtin', 'brand'] as $attribute) {
            if (! empty($data[$attribute])) {
                $this->writeGoogleElement($writer, $attribute, $data[$attribute]);
            }
        }

        $writer->endElement();
    }


    private function writeGoogleElement(XMLWriter $writer, string $name, string $value): void
    {
        $writer->startElement('g:' . $name);
        $writer->text($this->validXmlText($value));
        $writer->endElement();
    }


    private function validXmlText(string $value): string
    {
        return preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
    }


    private function absoluteImageUrl($image): string
    {
        $image = trim((string) $image);

        if ($image === '') {
            return '';
        }

        if (Str::startsWith($image, ['http://', 'https://', '//'])) {
            return $image;
        }

        $domain = rtrim((string) (config('settings.images_domain') ?: config('app.url')), '/');

        return $domain . '/' . ltrim($image, '/');
    }


    private function productType(Product $product): string
    {
        $group = config('seo.category_content.groups.' . $product->group . '.title')
            ?: Str::ucfirst(str_replace('-', ' ', (string) $product->group));

        if (! $product->relationLoaded('categories')) {
            return $group;
        }

        $category = $product->categories->first(fn ($item): bool => (int) $item->parent_id === 0);
        $subcategory = $category
            ? $product->categories->first(fn ($item): bool => (int) $item->parent_id === (int) $category->id)
            : null;

        return collect([$group, $category->title ?? null, $subcategory->title ?? null])
            ->map(fn ($value): string => Seo::normalizeDescription($value))
            ->filter()
            ->unique()
            ->implode(' > ');
    }


    private function currentPublicSalePrice(Product $product): ?float
    {
        $special = (float) $product->special;
        $regular = (float) $product->price;

        if ($special <= 0 || $special >= $regular || ($product->action && $product->action->coupon)) {
            return null;
        }

        try {
            $startsAt = $product->special_from && $product->special_from !== '0000-00-00 00:00:00'
                ? Carbon::parse($product->special_from)
                : null;
            $endsAt = $product->special_to && $product->special_to !== '0000-00-00 00:00:00'
                ? Carbon::parse($product->special_to)
                : null;
        } catch (\Throwable $exception) {
            return null;
        }

        if (($startsAt && $startsAt->isFuture()) || ($endsAt && $endsAt->isPast())) {
            return null;
        }

        return $special;
    }


    private function isbn13($value): ?string
    {
        $isbn = strtoupper(preg_replace('/[^0-9X]/i', '', (string) $value) ?? '');

        if (strlen($isbn) === 10 && $this->hasValidIsbn10Checksum($isbn)) {
            $isbn = '978' . substr($isbn, 0, 9);
            $isbn .= $this->gtin13CheckDigit($isbn);
        }

        if (strlen($isbn) !== 13 || ! preg_match('/^97[89]\d{10}$/', $isbn)) {
            return null;
        }

        return $this->gtin13CheckDigit(substr($isbn, 0, 12)) === (int) $isbn[12]
            ? $isbn
            : null;
    }


    private function hasValidIsbn10Checksum(string $isbn): bool
    {
        $sum = 0;

        for ($index = 0; $index < 10; $index++) {
            $digit = $isbn[$index] === 'X' ? 10 : (int) $isbn[$index];

            if ($digit === 10 && $index !== 9) {
                return false;
            }

            $sum += (10 - $index) * $digit;
        }

        return $sum % 11 === 0;
    }


    private function gtin13CheckDigit(string $firstTwelveDigits): int
    {
        $sum = 0;

        for ($index = 0; $index < 12; $index++) {
            $sum += (int) $firstTwelveDigits[$index] * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }
}
