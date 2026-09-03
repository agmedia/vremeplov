<?php

namespace App\Services\Payments;

use App\Models\Back\Orders\Order;
use Illuminate\Support\Facades\DB;

class ProviderReferenceService
{
    /**
     * Atomically bind a provider transaction reference to exactly one order.
     * The caller should already be inside its order transaction.
     */
    public function claim(Order $order, string $provider, string $reference): bool
    {
        $provider = strtolower(trim($provider));
        $reference = trim($reference);

        if (! preg_match('/^[a-z0-9_-]{1,32}$/D', $provider)
            || $reference === ''
            || mb_strlen($reference) > 191) {
            return false;
        }

        $now = now();

        DB::table('payment_provider_references')->insertOrIgnore([
            'provider' => $provider,
            'reference' => $reference,
            'order_id' => (int) $order->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $owner = DB::table('payment_provider_references')
            ->where('provider', $provider)
            ->where('reference', $reference)
            ->lockForUpdate()
            ->value('order_id');

        return $owner !== null && (int) $owner === (int) $order->id;
    }
}
