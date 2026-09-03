<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Helpers\Session\CheckoutSession;
use App\Models\Back\Orders\Order;
use App\Models\Front\Catalog\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderInventoryService
{
    private const HOLDING_STATUSES = [1, 2, 3, 4, 9, 10, 11];
    private const RELEASING_STATUSES = [5, 7];
    private const PASSIVE_STATUSES = [6, 12, 13, 14];

    public function reserve(
        Order $order,
        ?CarbonInterface $expiresAt = null,
        string $reason = 'checkout'
    ): Order {
        return $this->synchronize($order, $expiresAt, false, $reason);
    }

    public function synchronize(
        Order $order,
        ?CarbonInterface $expiresAt,
        bool $commit,
        string $reason
    ): Order {
        return DB::transaction(function () use ($order, $expiresAt, $commit, $reason) {
            $lockedOrder = $this->lockOrder((int) $order->id);
            $desiredItems = $this->orderItems((int) $lockedOrder->id);
            $existingItems = $this->activeReservationItems($lockedOrder);
            $wasCommitted = $lockedOrder->inventory_committed_at !== null;

            if ($this->isActive($lockedOrder) && $this->sameItems($desiredItems, $existingItems)) {
                $updates = [
                    'inventory_allocation_error' => null,
                ];

                if (! $wasCommitted && ! $commit) {
                    $updates['inventory_reservation_expires_at'] = $expiresAt;
                }

                if ($commit && ! $wasCommitted) {
                    $updates['inventory_committed_at'] = now();
                    $updates['inventory_reservation_expires_at'] = null;
                }

                // These are trusted internal state fields. forceFill avoids a
                // stale Eloquent guardable-column cache silently dropping newly
                // migrated columns in a long-running PHP worker.
                $lockedOrder->forceFill($updates)->saveOrFail();

                return $lockedOrder->fresh();
            }

            $productIds = collect(array_keys($desiredItems))
                ->merge(array_keys($existingItems))
                ->unique()
                ->sort()
                ->values()
                ->all();
            $products = $this->lockProducts($productIds);

            if ($this->isActive($lockedOrder)) {
                $this->releaseLocked($lockedOrder, $products, $reason . ':replace');
                $lockedOrder->refresh();
            }

            $this->reserveLocked(
                $lockedOrder,
                $desiredItems,
                $products,
                $expiresAt,
                $commit || $wasCommitted,
                $reason
            );

            return $lockedOrder->fresh();
        });
    }

    public function commit(Order $order, string $reason = 'order_confirmed'): Order
    {
        return $this->synchronize($order, null, true, $reason);
    }

    public function release(Order $order, string $reason = 'order_released'): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $lockedOrder = $this->lockOrder((int) $order->id);

            if (! $this->isActive($lockedOrder)) {
                return $lockedOrder;
            }

            $items = $this->activeReservationItems($lockedOrder);
            $products = $this->lockProducts(array_keys($items));
            $this->releaseLocked($lockedOrder, $products, $reason);

            return $lockedOrder->fresh();
        });
    }

    public function applyStatusTransition(
        Order $order,
        int $previousStatus,
        int $targetStatus,
        string $reason = 'status_change'
    ): Order {
        if (in_array($targetStatus, self::RELEASING_STATUSES, true)) {
            return $this->release($order, $reason);
        }

        if (in_array($targetStatus, self::PASSIVE_STATUSES, true)) {
            return $this->releaseUncommitted($order, $reason);
        }

        if (! in_array($targetStatus, self::HOLDING_STATUSES, true)) {
            return $order->fresh();
        }

        $reactivationSource = in_array($previousStatus, self::RELEASING_STATUSES, true)
            || $previousStatus === (int) config('settings.order.status.call_when_found');
        $manualReactivation = $reactivationSource
            && strpos($reason, 'admin_status:') === 0;

        // A provider/browser return must never revive an order that was already
        // canceled, declined or moved to "call when found". Even if a stale
        // active reservation marker exists, only an explicit admin action may
        // turn that allocation into committed stock.
        if ($reactivationSource && ! $manualReactivation) {
            return $order->fresh();
        }

        if ($this->isActive($order)) {
            return $this->commit($order, $reason);
        }

        if ($previousStatus === (int) config('settings.order.status.unfinished')) {
            return $this->commit($order, $reason);
        }

        if ($manualReactivation) {
            return $this->commit($order, $reason);
        }

        // Legacy orders have no reliable record proving that the old /uspjeh
        // endpoint did or did not reduce stock. Never invent a reservation for
        // a holding -> holding status change; such orders require manual audit.
        return $order->fresh();
    }

    private function releaseUncommitted(Order $order, string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $lockedOrder = $this->lockOrder((int) $order->id);

            if (! $this->isActive($lockedOrder) || $lockedOrder->inventory_committed_at !== null) {
                return $lockedOrder;
            }

            $items = $this->activeReservationItems($lockedOrder);
            $products = $this->lockProducts(array_keys($items));
            $this->releaseLocked($lockedOrder, $products, $reason);

            return $lockedOrder->fresh();
        });
    }

    public function reservationMatchesOrder(Order $order): bool
    {
        return $this->isActive($order)
            && $this->sameItems(
                $this->orderItems((int) $order->id),
                $this->activeReservationItems($order)
            );
    }

    public function availableQuantities(array $productIds, ?int $currentOrderId = null): Collection
    {
        $ids = collect($productIds)->map(function ($id) {
            return (int) $id;
        })->filter()->unique()->values();

        $available = DB::table('products')
            ->whereIn('id', $ids)
            ->pluck('quantity', 'id')
            ->map(function ($quantity) {
                return (int) $quantity;
            });

        if (! $currentOrderId) {
            return $available;
        }

        $order = Order::query()->find($currentOrderId);

        if (! $order || ! $this->isActive($order) || $order->inventory_committed_at !== null) {
            return $available;
        }

        foreach ($this->activeReservationItems($order) as $productId => $quantity) {
            if ($ids->contains((int) $productId)) {
                $available->put((int) $productId, (int) $available->get($productId, 0) + $quantity);
            }
        }

        return $available;
    }

    public function availableForCurrentCheckout(array $productIds): Collection
    {
        $sessionOrder = CheckoutSession::getOrder();
        $orderId = (int) data_get($sessionOrder, 'id', 0);

        return $this->availableQuantities($productIds, $orderId ?: null);
    }

    public function expireReservations(int $limit = 100): int
    {
        $orderIds = Order::query()
            ->whereNotNull('inventory_reserved_at')
            ->whereNull('inventory_committed_at')
            ->whereNull('inventory_released_at')
            ->whereNotNull('inventory_reservation_expires_at')
            ->where('inventory_reservation_expires_at', '<=', now())
            ->where('order_status_id', config('settings.order.status.unfinished'))
            ->orderBy('inventory_reservation_expires_at')
            ->limit($limit)
            ->pluck('id');

        $released = 0;

        foreach ($orderIds as $orderId) {
            if ($this->expireReservation((int) $orderId)) {
                $released++;
            }
        }

        return $released;
    }

    private function expireReservation(int $orderId): bool
    {
        return DB::transaction(function () use ($orderId) {
            $order = $this->lockOrder($orderId);

            if (! $this->isActive($order)
                || $order->inventory_committed_at !== null
                || $order->inventory_reservation_expires_at === null
                || $order->inventory_reservation_expires_at->isFuture()
                || (int) $order->order_status_id !== (int) config('settings.order.status.unfinished')) {
                return false;
            }

            $items = $this->activeReservationItems($order);
            $products = $this->lockProducts(array_keys($items));
            $this->releaseLocked($order, $products, 'reservation_expired');

            return true;
        });
    }

    public function recordAllocationError(Order $order, \Throwable $exception): void
    {
        $message = mb_substr($exception->getMessage(), 0, 500);

        Order::query()->where('id', $order->id)->update([
            'inventory_allocation_error' => $message,
        ]);

        Log::critical('Inventory allocation failed for confirmed order', [
            'order_id' => $order->id,
            'error' => $message,
        ]);
    }

    public function isActive(Order $order): bool
    {
        return $order->inventory_reserved_at !== null && $order->inventory_released_at === null;
    }

    private function lockOrder(int $orderId): Order
    {
        return Order::query()->where('id', $orderId)->lockForUpdate()->firstOrFail();
    }

    private function orderItems(int $orderId): array
    {
        $missingProduct = DB::table('order_products as op')
            ->leftJoin('products as p', 'p.id', '=', 'op.product_id')
            ->where('op.order_id', $orderId)
            ->whereNull('p.id')
            ->select('op.product_id', 'op.name', 'op.quantity')
            ->first();

        if ($missingProduct) {
            throw new InsufficientStockException(
                (int) $missingProduct->product_id,
                (string) ($missingProduct->name ?: 'Obrisani proizvod'),
                0,
                (int) $missingProduct->quantity
            );
        }

        return DB::table('order_products as op')
            ->join('products as p', 'p.id', '=', 'op.product_id')
            ->where('op.order_id', $orderId)
            ->where('p.decrease', 1)
            ->select('op.product_id', DB::raw('SUM(op.quantity) as quantity'))
            ->groupBy('op.product_id')
            ->orderBy('op.product_id')
            ->get()
            ->mapWithKeys(function ($item) {
                return [(int) $item->product_id => (int) $item->quantity];
            })
            ->filter(function ($quantity) {
                return $quantity > 0;
            })
            ->all();
    }

    private function activeReservationItems(Order $order): array
    {
        if (! $this->isActive($order) || (int) $order->inventory_reservation_version < 1) {
            return [];
        }

        return DB::table('inventory_movements')
            ->where('order_id', $order->id)
            ->where('reservation_version', $order->inventory_reservation_version)
            ->where('action', 'reserve')
            ->pluck('quantity', 'product_id')
            ->map(function ($quantity) {
                return (int) $quantity;
            })
            ->all();
    }

    private function lockProducts(array $productIds): Collection
    {
        if (! $productIds) {
            return collect();
        }

        return DB::table('products')
            ->whereIn('id', $productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    private function sameItems(array $desiredItems, array $existingItems): bool
    {
        ksort($desiredItems);
        ksort($existingItems);

        return $desiredItems === $existingItems;
    }

    private function reserveLocked(
        Order $order,
        array $items,
        Collection $products,
        ?CarbonInterface $expiresAt,
        bool $commit,
        string $reason
    ): void {
        $version = (int) $order->inventory_reservation_version + 1;
        $now = now();
        $movements = [];

        foreach ($items as $productId => $quantity) {
            $product = $products->get($productId);

            if (! $product) {
                throw new InsufficientStockException($productId, 'Obrisani proizvod', 0, $quantity);
            }

            if (! (bool) $product->decrease) {
                continue;
            }

            $available = (int) $product->quantity;

            if ($available < $quantity) {
                throw new InsufficientStockException($productId, (string) $product->name, $available, $quantity);
            }

            $updated = DB::table('products')
                ->where('id', $productId)
                ->where('quantity', '>=', $quantity)
                ->decrement('quantity', $quantity);

            if ($updated !== 1) {
                throw new InsufficientStockException($productId, (string) $product->name, $available, $quantity);
            }

            $movements[] = [
                'order_id' => (int) $order->id,
                'product_id' => $productId,
                'reservation_version' => $version,
                'action' => 'reserve',
                'quantity' => $quantity,
                'stock_before' => $available,
                'stock_after' => $available - $quantity,
                'reason' => $reason,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $product->quantity = $available - $quantity;
        }

        if ($movements) {
            DB::table('inventory_movements')->insert($movements);
        }

        $order->forceFill([
            'inventory_reserved_at' => $now,
            'inventory_committed_at' => $commit ? $now : null,
            'inventory_released_at' => null,
            'inventory_reservation_expires_at' => $commit ? null : $expiresAt,
            'inventory_reservation_version' => $version,
            'inventory_allocation_error' => null,
        ])->saveOrFail();
    }

    private function releaseLocked(Order $order, Collection $products, string $reason): void
    {
        $items = $this->activeReservationItems($order);
        $now = now();
        $movements = [];

        foreach ($items as $productId => $quantity) {
            $product = $products->get($productId);

            if (! $product) {
                continue;
            }

            $before = (int) $product->quantity;
            DB::table('products')->where('id', $productId)->increment('quantity', $quantity);

            $movements[] = [
                'order_id' => (int) $order->id,
                'product_id' => $productId,
                'reservation_version' => (int) $order->inventory_reservation_version,
                'action' => 'release',
                'quantity' => $quantity,
                'stock_before' => $before,
                'stock_after' => $before + $quantity,
                'reason' => $reason,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $product->quantity = $before + $quantity;
        }

        if ($movements) {
            DB::table('inventory_movements')->insert($movements);
        }

        $order->forceFill([
            'inventory_released_at' => $now,
            'inventory_reservation_expires_at' => null,
            'inventory_allocation_error' => null,
        ])->saveOrFail();
    }
}
