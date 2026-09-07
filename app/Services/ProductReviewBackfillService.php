<?php

namespace App\Services;

use App\Models\ProductReviewBackfill;
use App\Models\ProductReviewBackfillItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductReviewBackfillService
{
    private ?bool $available = null;

    private ProductReviewRequestService $requests;

    public function __construct(ProductReviewRequestService $requests)
    {
        $this->requests = $requests;
    }

    public function isAvailable(): bool
    {
        if ($this->available === null) {
            $this->available = Schema::hasTable('product_review_backfills')
                && Schema::hasTable('product_review_backfill_items');
        }

        return $this->available;
    }

    public function candidateQuery(Carbon $from, Carbon $to): Builder
    {
        $query = $this->requests->eligibleOrders($from, $to);

        if ($this->isAvailable()) {
            $query->whereNotExists(function ($items) {
                $items->select(DB::raw(1))
                    ->from('product_review_backfill_items')
                    ->join(
                        'product_review_backfills',
                        'product_review_backfills.id',
                        '=',
                        'product_review_backfill_items.backfill_id'
                    )
                    ->whereColumn('product_review_backfill_items.order_id', 'orders.id')
                    ->whereIn('product_review_backfills.status', [
                        ProductReviewBackfill::STATUS_PENDING,
                        ProductReviewBackfill::STATUS_RUNNING,
                    ]);
            });
        }

        return $query;
    }

    /**
     * @return array{eligible_count:int, orders:\Illuminate\Database\Eloquent\Collection}
     */
    public function selectCandidates(Carbon $from, Carbon $to, int $limit): array
    {
        $query = $this->candidateQuery($from, $to);
        $eligibleCount = (clone $query)->reorder()->count('orders.id');
        $orders = $query->limit(max(1, $limit))->get();

        return [
            'eligible_count' => $eligibleCount,
            'orders' => $orders,
        ];
    }

    public function create(
        Carbon $from,
        Carbon $to,
        int $limit,
        int $intervalSeconds,
        ?int $createdBy = null
    ): ProductReviewBackfill {
        return DB::transaction(function () use ($from, $to, $limit, $intervalSeconds, $createdBy) {
            $selection = $this->selectCandidates($from, $to, $limit);
            $orders = $selection['orders'];

            $batch = ProductReviewBackfill::query()->create([
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'requested_limit' => $limit,
                'interval_seconds' => $intervalSeconds,
                'eligible_count' => $selection['eligible_count'],
                'total_count' => $orders->count(),
                'status' => $orders->isEmpty()
                    ? ProductReviewBackfill::STATUS_COMPLETED
                    : ProductReviewBackfill::STATUS_PENDING,
                'created_by' => $createdBy,
                'finished_at' => $orders->isEmpty() ? now() : null,
            ]);

            $now = now();
            foreach ($orders->pluck('id')->chunk(500) as $orderIds) {
                ProductReviewBackfillItem::query()->insert(
                    $orderIds->map(fn ($orderId) => [
                        'backfill_id' => $batch->id,
                        'order_id' => $orderId,
                        'status' => ProductReviewBackfillItem::STATUS_PENDING,
                        'attempts' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            }

            return $batch->fresh();
        });
    }
}
