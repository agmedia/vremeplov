<?php

namespace App\Models\Back\Catalog\Product;

use App\Models\Back\Catalog\Category;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{

    /**
     * @var string $table
     */
    protected $table = 'product_category';

    /**
     * @var array $guarded
     */
    protected $guarded = [];


    /**
     * Update Product categories.
     *
     * @param array $categories
     * @param int   $product_id
     *
     * @return array
     */
    public static function storeData(array $categories, int $product_id): array
    {
        $created = [];
        self::where('product_id', $product_id)->delete();

        foreach ($categories as $category) {
            $cat = Category::find($category);

            if ($cat) {
                $created[] = self::insert([
                    'product_id'  => $product_id,
                    'category_id' => $category
                ]);
            }
        }

        return $created;
    }
}
