<?php

namespace App\Helpers;

use App\Models\Back\Catalog\Product\Product;

/**
 *
 */
class Njuskalo
{

    /**
     * @var array
     */
    private $response = [];


    /**
     * @return array
     */
    public function getItems(): array
    {
        $products = Product::query()->where('status', 1)
                                    ->where('price', '!=', 0)
                                    ->where('quantity', '!=', 0)
                                    ->select('id', 'name', 'description', 'quantity', 'status', 'price', 'group', 'image', 'pages', 'dimensions', 'origin', 'letter', 'condition', 'binding', 'year')
                                    ->get();

        foreach ($products as $product) {
            $this->response[] = [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $this->getDescription($product),
                'group' => config('settings.njuskalo.sync.' . $product->group),
                'price' => $product->price,
                'image' => asset($product->image),
            ];
        }

        return $this->response;
    }


    /**
     * @param Product $product
     *
     * @return string
     */
    private function getDescription(Product $product): string
    {
        $str = '';

        if ($product->description != '') {
            $str .= $product->description . '<br><br>';
        }
        if ($product->pages) {
            $str .= 'Stranica: ' . $product->pages . '<br>';
        }
        if ($product->dimensions) {
            $str .= 'Dimenzije: ' . $product->dimensions . '<br>';
        }
        if ($product->origin) {
            $str .= 'Jezik: ' . $product->origin . '<br>';
        }
        if ($product->letter) {
            $str .= 'Pismo: ' . $product->letter . '<br>';
        }
        if ($product->condition) {
            $str .= 'Stanje: ' . $product->condition . '<br>';
        }
        if ($product->binding) {
            $str .= 'Uvez: ' . $product->binding . '<br>';
        }
        if ($product->year) {
            $str .= 'Godina: ' . $product->year . '<br>';
        }

        return $str;
    }
}