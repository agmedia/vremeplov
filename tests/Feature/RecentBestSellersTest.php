<?php

namespace Tests\Feature;

use App\Services\ProductRecommendationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecentBestSellersTest extends TestCase
{
    /** @var string */
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'best_sellers_testing',
            'database.connections.best_sellers_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'settings.order.turnover_statuses' => [4],
        ]);

        DB::purge('best_sellers_testing');
        Cache::flush();
        Carbon::setTestNow('2026-09-09 12:00:00');

        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('author_id')->default(0);
            $table->unsignedBigInteger('action_id')->default(0);
            $table->boolean('status')->default(true);
            $table->decimal('price', 15, 4)->default(10);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('image')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('order_status_id');
            $table->timestamps();
        });

        Schema::create('order_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('quantity');
        });

        Schema::create('authors', function (Blueprint $table) {
            $table->bigIncrements('id');
        });

        Schema::create('product_actions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('status')->default(true);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->bigIncrements('id');
        });

        Schema::create('product_category', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('category_id');
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();
        DB::purge('best_sellers_testing');
        config(['database.default' => $this->originalConnection]);

        parent::tearDown();
    }

    public function test_it_ranks_recent_available_products_and_honours_exclusions(): void
    {
        $now = now();

        DB::table('products')->insert([
            ['id' => 1, 'image' => 'one.jpg', 'quantity' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'image' => 'two.jpg', 'quantity' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'image' => 'three.jpg', 'quantity' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'image' => 'four.jpg', 'quantity' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('orders')->insert([
            ['id' => 1, 'order_status_id' => 4, 'created_at' => $now->copy()->subDays(2), 'updated_at' => $now],
            ['id' => 2, 'order_status_id' => 4, 'created_at' => $now->copy()->subDays(31), 'updated_at' => $now],
        ]);

        DB::table('order_products')->insert([
            ['order_id' => 1, 'product_id' => 1, 'quantity' => 2],
            ['order_id' => 1, 'product_id' => 2, 'quantity' => 5],
            ['order_id' => 1, 'product_id' => 3, 'quantity' => 20],
            ['order_id' => 2, 'product_id' => 4, 'quantity' => 20],
        ]);

        $ids = app(ProductRecommendationService::class)
            ->recentBestSellers(30, 10, [2])
            ->pluck('id')
            ->all();

        $this->assertSame([1], $ids);
    }
}
