<?php

namespace App\Console\Commands;

use App\Helpers\ProductHelper;
use App\Http\Controllers\Back\DashboardController;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Settings\Api\OC_Import;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class CleanProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean:products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean & resolve all products groups, categories and url.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $import = new OC_Import();
        $range = $import->resolveProductsImportRange()->first();
        $products = Product::query()->offset($range->offset)->take($range->limit)->with('categories')->get();

        foreach ($products as $item) {
            if ($item->group && isset($item->categories()->first()->group)) {
                $item->update([
                    'url'             => ProductHelper::url($item),
                    'category_string' => ProductHelper::categoryString($item)
                ]);

            } else {
                $cats = $import->getProductCategories($item->ean);

                if ($cats->count()) {
                    $path = $import->getCategoryPath($cats->first()->category_id);

                    if (isset($path->keyword)) {
                        $item->update([
                            'group' => $path->keyword
                        ]);
                    }
                }
            }
        }

        $import->resolveProductsImportRange(($range->offset + $range->limit), $range->limit);

        return response()->json(['success' => 'Import je uspješno obavljen..!']);
    }
}
