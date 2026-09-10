<?php

namespace App\Services\Orders;

use App\Models\Back\Orders\Order;
use Illuminate\Support\Facades\DB;

class CheckoutOrderGuardService
{
    /**
     * The same customer and the same quantities represent one active checkout.
     */
    public function fingerprint(array $data): string
    {
        $address = (array) data_get($data, 'address', []);
        $items = collect(data_get($data, 'cart.items', []))
            ->mapToGroups(function ($item) {
                return [(int) data_get($item, 'id') => (int) data_get($item, 'quantity')];
            })
            ->map(function ($quantities) {
                return $quantities->sum();
            })
            ->sortKeys()
            ->all();

        return $this->makeFingerprint((string) ($address['email'] ?? ''), $items);
    }

    /**
     * Create and lock the guard row before looking for an unfinished order.
     * Concurrent preview requests with the same fingerprint are serialized here.
     */
    public function lockMatchingUnfinished(string $fingerprint): ?Order
    {
        $now = now();

        DB::table('checkout_order_guards')->insertOrIgnore([
            'fingerprint' => $fingerprint,
            'order_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $guard = DB::table('checkout_order_guards')
            ->where('fingerprint', $fingerprint)
            ->lockForUpdate()
            ->first();

        if (! $guard || ! $guard->order_id) {
            return null;
        }

        $order = Order::query()->whereKey($guard->order_id)->lockForUpdate()->first();

        if (! $order
            || (int) $order->order_status_id !== (int) config('settings.order.status.unfinished')
            || ! hash_equals($fingerprint, $this->fingerprintForOrder($order))) {
            return null;
        }

        return $order;
    }

    public function remember(string $fingerprint, int $orderId): void
    {
        DB::table('checkout_order_guards')
            ->where('fingerprint', $fingerprint)
            ->update([
                'order_id' => $orderId,
                'updated_at' => now(),
            ]);
    }

    private function fingerprintForOrder(Order $order): string
    {
        $items = DB::table('order_products')
            ->where('order_id', $order->id)
            ->select('product_id', DB::raw('SUM(quantity) AS quantity'))
            ->groupBy('product_id')
            ->orderBy('product_id')
            ->get()
            ->mapWithKeys(function ($item) {
                return [(int) $item->product_id => (int) $item->quantity];
            })
            ->all();

        return $this->makeFingerprint((string) $order->payment_email, $items);
    }

    private function makeFingerprint(string $email, array $items): string
    {
        return hash('sha256', json_encode([
            'email' => mb_strtolower(trim($email), 'UTF-8'),
            'items' => $items,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
