<?php

namespace App\Console\Commands;

use App\Models\Back\Orders\Order;
use App\Services\Shipping\BoxNowService;
use App\Services\Shipping\GlsTrackingService;
use App\Services\Shipping\OrderTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncShipmentTracking extends Command
{
    protected $signature = 'sync:shipment-tracking
        {--limit=50 : Maximum number of orders to refresh}
        {--stale-minutes=15 : Refresh orders older than this many minutes}';

    protected $description = 'Osvježava statuse aktivnih GLS i Box Now pošiljaka.';

    public function handle(OrderTrackingService $trackingService): int
    {
        if (! Schema::hasColumn('orders', 'shipping_tracking_status_code')) {
            $this->info('Polja za praćenje pošiljaka još nisu dostupna.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $staleMinutes = max(1, (int) $this->option('stale-minutes'));
        $orders = Order::query()
            ->where(function ($query) {
                $query->whereIn('shipping_carrier', [GlsTrackingService::CARRIER, BoxNowService::CARRIER])
                    ->orWhere('shipping_method', 'like', '%GLS%')
                    ->orWhere('shipping_method', 'like', '%BoxNow%')
                    ->orWhere('shipping_method', 'like', '%Box Now%')
                    ->orWhere('shipping_code', 'like', '%gls%')
                    ->orWhere('shipping_code', 'like', '%boxnow%');
            })
            ->where(function ($query) {
                $query->where(function ($trackingQuery) {
                    $trackingQuery->whereNotNull('tracking_code')
                        ->where('tracking_code', '<>', '');
                })->orWhere(function ($trackingQuery) {
                    $trackingQuery->whereNotNull('shipping_parcel_id')
                        ->where('shipping_parcel_id', '<>', '');
                });
            })
            ->where(function ($query) {
                $query->whereNull('shipping_tracking_status_code')
                    ->orWhereNotIn('shipping_tracking_status_code', array_merge(
                        ['5', '23', '40', '92'],
                        BoxNowService::terminalStatusCodes()
                    ));
            })
            ->where(function ($query) use ($staleMinutes) {
                $query->whereNull('shipping_tracking_attempted_at')
                    ->orWhere('shipping_tracking_attempted_at', '<=', now()->subMinutes($staleMinutes));
            })
            ->orderByRaw('shipping_tracking_attempted_at IS NULL DESC')
            ->orderBy('shipping_tracking_attempted_at')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $updated = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                $order->forceFill(['shipping_tracking_attempted_at' => now()])->save();
                $this->releaseDatabaseConnection();
                $result = $trackingService->refresh($order);

                if ($result['updated']) {
                    $updated++;
                }
            } catch (\Throwable $exception) {
                $failed++;

                Log::warning('Scheduled shipment tracking refresh failed.', [
                    'order_id' => $order->id,
                    'error' => $exception->getMessage(),
                ]);
            } finally {
                $this->releaseDatabaseConnection();
            }
        }

        $this->info("Tracking pošiljaka osvježen. Ažurirano: {$updated}. Neuspjelo: {$failed}.");
        $this->releaseDatabaseConnection();

        return self::SUCCESS;
    }

    private function releaseDatabaseConnection(): void
    {
        try {
            DB::disconnect();
        } catch (\Throwable $exception) {
            // Best-effort release while waiting on carrier APIs or mail delivery.
        }
    }
}
