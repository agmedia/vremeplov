<?php

namespace App\Console\Commands;

use App\Models\Back\Catalog\Product\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
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
        $log_start = microtime(true);

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

        $log_end = microtime(true);
        Log::info('__Clean & resolve all products slugs. - Total Execution Time: ' . number_format(($log_end - $log_start), 2, ',', '.') . ' sec.');

        return 1;
    }
}
