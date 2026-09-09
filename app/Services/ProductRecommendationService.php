<?php

namespace App\Services;

use App\Helpers\OrderHelper;
use App\Models\Front\Catalog\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductRecommendationService
{
    public function recentBestSellers(int $days = 30, int $limit = 10, array $excludedProductIds = []): Collection
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('orders') || ! Schema::hasTable('order_products')) {
            return collect();
        }

        $days = max(1, min($days, 365));
        $limit = max(1, min($limit, 50));
        $excludedProductIds = array_values(array_unique(array_map(
            'intval',
            array_filter($excludedProductIds, static fn ($id) => (int) $id > 0)
        )));
        sort($excludedProductIds);

        $statuses = OrderHelper::turnoverStatuses();
        $cacheKey = 'recommendations.recent-best-sellers.v1.' . sha1(json_encode([
            'days' => $days,
            'limit' => $limit,
            'statuses' => $statuses,
            'excluded_product_ids' => $excludedProductIds,
        ]));

        $productIds = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($days, $limit, $statuses, $excludedProductIds) {
            $ranking = DB::table('order_products')
                ->join('orders', 'orders.id', '=', 'order_products.order_id')
                ->join('products', 'products.id', '=', 'order_products.product_id')
                ->whereIn('orders.order_status_id', $statuses)
                ->whereBetween('orders.created_at', [now()->subDays($days), now()])
                ->where('order_products.product_id', '>', 0)
                ->where('order_products.quantity', '>', 0)
                ->where('products.status', 1)
                ->where('products.quantity', '>', 0)
                ->where('products.price', '!=', 0)
                ->whereNotNull('products.image')
                ->where('products.image', '!=', '')
                ->select('order_products.product_id')
                ->selectRaw('SUM(order_products.quantity) AS sold_quantity')
                ->selectRaw('MAX(orders.created_at) AS last_sold_at')
                ->groupBy('order_products.product_id')
                ->orderByDesc('sold_quantity')
                ->orderByDesc('last_sold_at')
                ->orderByDesc('order_products.product_id');

            if ($excludedProductIds) {
                $ranking->whereNotIn('order_products.product_id', $excludedProductIds);
            }

            return $ranking->limit($limit)
                ->pluck('order_products.product_id')
                ->map(fn ($id) => (int) $id);
        });

        $productIds = collect($productIds)->map(fn ($id) => (int) $id)->values();
        if ($productIds->isEmpty()) {
            return collect();
        }

        $positions = $productIds->flip();

        return Product::query()
            ->active()
            ->hasStock()
            ->whereIn('id', $productIds)
            ->with(['author', 'action', 'categories'])
            ->get()
            ->sortBy(fn (Product $product) => $positions->get($product->id, PHP_INT_MAX))
            ->take($limit)
            ->values();
    }
}
