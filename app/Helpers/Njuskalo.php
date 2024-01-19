<?php

namespace App\Helpers;

use App\Models\Back\Catalog\Product\Product;

/**
 *
 */
class Njuskalo
{

    private $response = [];


    public function getItems(): array
    {
        $products = Product::query()->where('status', 1)
                                    ->where('price', '!=', 0)
                                    ->where('quantity', '!=', 0)
                                    ->select('id', 'name', 'price', 'group', 'description', 'image')
                                    ->get();

        foreach ($products as $product) {
            $this->response[] = [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'group' => config('settings.njuskalo.sync.' . $product->group),
                'price' => $product->price,
                'image' => $product->image,
            ];
        }

        return $this->response;
    }
}