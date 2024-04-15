<?php

namespace App\Console\Commands;

use App\Models\Back\Catalog\Product\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProductsSlugRevision extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean:product_slugs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean & resolve all products slugs.';

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
        $slugs = Product::query()->groupBy('slug')->havingRaw('COUNT(id) > 1')->pluck('slug', 'id')->toArray();

        foreach ($slugs as $slug) {
            $products = Product::where('slug', $slug)->get();

            if ($products) {
                foreach ($products as $product) {
                    $time = Str::random(9);
                    $product->update([
                        'slug' => $product->slug . '-' . $time,
                        'url'  => $product->url . '-' . $time,
                    ]);
                }
            }
        }

        return response()->json(['success' => 'Import atributa iz opisa je uspješno obavljen..!']);
    }
}
