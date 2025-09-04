<?php

namespace App\Console\Commands;

use App\Helpers\ProductHelper;
use App\Http\Controllers\Back\DashboardController;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Settings\Api\OC_Import;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CleanProductDescriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean:descriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean & resolve all products descriptions.';

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
        $total_products = Product::query()->count();
        $products = Product::query()->offset($range->offset)->take($range->limit)->get();

        if ($range->offset > $total_products) {
            return response()->json(['success' => 'Import je gotov..!']);
        }

        foreach ($products as $item) {
            $seo = DB::connection('oc')->table('oc_seo_url')->where('query', '=', 'product_id=' . $item->ean)->first();

            if ($seo) {
                $item->update([
                    'slug' => $seo->keyword
                ]);

                $item->update([
                    'url' => ProductHelper::url($item)
                ]);
            }
        }

        $import->resolveProductsImportRange(($range->offset + $range->limit), $range->limit);

        return response()->json(['success' => 'Import atributa iz opisa je uspješno obavljen..!']);
    }
}
