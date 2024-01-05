<?php

namespace App\Console\Commands;

use App\Helpers\ProductHelper;
use App\Http\Controllers\Back\DashboardController;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Settings\Api\OC_Import;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

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
        $products = Product::query()->offset($range->offset)->take($range->limit)->get();

        foreach ($products as $item) {
            $attributes = $import->resolveAttributes($item->description);
            $publisher = $import->resolvePublisher(isset($attributes['Izdavač']) ? $attributes['Izdavač'] : '');

            $item->update([
                'publisher_id'     => $publisher,
                'sku'              => isset($attributes['Šifra']) ? $attributes['Šifra'] : $item->model . '-' . $item->product_id,
                'pages'            => isset($attributes['Broj stranica']) ? $attributes['Broj stranica'] : null,
                'origin'           => isset($attributes['Jezik']) ? $attributes['Jezik'] : null,
                'letter'           => isset($attributes['Pismo']) ? $attributes['Pismo'] : null,
                'condition'        => isset($attributes['Stanje']) ? $attributes['Stanje'] : null,
                'binding'          => isset($attributes['Uvez']) ? $attributes['Uvez'] : null,
                'year'             => isset($attributes['Godina']) ? str_replace('.', '', $attributes['Godina']) : null,
            ]);
        }

        $import->resolveProductsImportRange(($range->offset + $range->limit), $range->limit);

        return response()->json(['success' => 'Import atributa iz opisa je uspješno obavljen..!']);
    }
}
