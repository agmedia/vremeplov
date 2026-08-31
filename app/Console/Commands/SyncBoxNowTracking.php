<?php

namespace App\Console\Commands;

use App\Models\Back\Orders\Order;
use App\Services\Shipping\BoxNowService;
use App\Services\Shipping\OrderTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncBoxNowTracking extends Command
{
    protected $signature = 'sync:boxnow-tracking
        {--limit=50 : Najveći broj narudžbi za jedno osvježavanje}
        {--stale-minutes=15 : Osvježi statuse starije od ovoliko minuta}';

    protected $description = 'Osvježava statuse aktivnih Box Now pošiljaka.';

    public function handle(OrderTrackingService $trackingService): int
    {
        foreach ([
            'shipping_carrier',
            'shipping_parcel_id',
            'shipping_tracking_status_code',
            'shipping_tracking_updated_at',
            'shipping_tracking_attempted_at',
        ] as $column) {
            if (! Schema::hasColumn('orders', $column)) {
                $this->info('Box Now tracking polja još nisu dostupna.');

                return self::SUCCESS;
            }
        }

        $limit = max(1, (int) $this->option('limit'));
        $staleMinutes = max(1, (int) $this->option('stale-minutes'));
        $orders = Order::query()
            ->where(function ($query) {
                $query->where('shipping_carrier', BoxNowService::CARRIER)
                    ->orWhere(function ($legacyQuery) {
                        $legacyQuery->where(function ($carrierQuery) {
                            $carrierQuery->whereNull('shipping_carrier')
                                ->orWhere('shipping_carrier', '');
                        })->where(function ($shippingQuery) {
                            $shippingQuery->where('shipping_method', 'like', '%BoxNow%')
                                ->orWhere('shipping_method', 'like', '%Box Now%')
                                ->orWhere('shipping_code', 'boxnow');
                        });
                    });
            })
            ->where(function ($query) {
                $query->where(function ($trackingQuery) {
                    $trackingQuery->whereNotNull('shipping_parcel_id')
                        ->where('shipping_parcel_id', '<>', '');
                })->orWhere(function ($trackingQuery) {
                    $trackingQuery->where('shipping_carrier', BoxNowService::CARRIER)
                        ->whereNotNull('tracking_code')
                        ->where('tracking_code', '<>', '');
                });
            })
            ->where(function ($query) {
                $query->whereNull('shipping_tracking_status_code')
                    ->orWhereNotIn('shipping_tracking_status_code', BoxNowService::terminalStatusCodes());
            })
            ->where(function ($query) use ($staleMinutes) {
                $query->whereNull('shipping_tracking_attempted_at')
                    ->orWhere('shipping_tracking_attempted_at', '<=', now()->subMinutes($staleMinutes));
            })
            ->orderByRaw('shipping_tracking_attempted_at IS NULL DESC')
            ->orderBy('shipping_tracking_attempted_at')
            ->limit($limit)
            ->get();

        $updated = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                // Bilježi i neuspjeli pokušaj kako isti loš parcel ne bi
                // trajno zauzeo ograničeni batch i blokirao ostale narudžbe.
                $order->forceFill(['shipping_tracking_attempted_at' => now()])->save();
                $result = $trackingService->refreshBoxNow($order);

                if ($result['updated']) {
                    $updated++;
                }
            } catch (\Throwable $exception) {
                $failed++;
                Log::warning('Scheduled Box Now tracking refresh failed.', [
                    'order_id' => $order->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("Box Now tracking osvježen. Ažurirano: {$updated}. Neuspjelo: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
