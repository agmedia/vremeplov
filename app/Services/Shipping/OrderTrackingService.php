<?php

namespace App\Services\Shipping;

use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderHistory;
use Carbon\Carbon;
use RuntimeException;
use Illuminate\Support\Facades\Cache;

class OrderTrackingService
{
    private const REFRESH_LOCK_SECONDS = 90;

    /** @var BoxNowService */
    private $boxNow;

    public function __construct(BoxNowService $boxNow)
    {
        $this->boxNow = $boxNow;
    }

    public function refreshBoxNow(Order $order): array
    {
        if (! $this->isBoxNowOrder($order)) {
            throw new RuntimeException('Narudžba nema odabranu Box Now dostavu.');
        }

        $lock = Cache::lock(
            'boxnow-tracking-refresh:' . $order->id,
            self::REFRESH_LOCK_SECONDS
        );

        if (! $lock->get()) {
            return [
                'updated' => false,
                'message' => 'Osvježavanje BOX NOW statusa za ovu narudžbu već je u tijeku.',
                'tracking' => [],
            ];
        }

        try {
            if ($order->exists) {
                $order->refresh();
            }

            if (! $this->isBoxNowOrder($order)) {
                throw new RuntimeException('Narudžba nema odabranu Box Now dostavu.');
            }

            return $this->apply($order, $this->boxNow->track($order));
        } finally {
            $lock->release();
        }
    }

    public function apply(Order $order, array $tracking, bool $writeHistory = true): array
    {
        if ($order->exists) {
            $order->refresh();
        }

        $trackedAt = $this->trackedAt($tracking['tracked_at'] ?? null);
        $currentTrackedAt = $order->shipping_tracking_updated_at
            ? Carbon::make($order->shipping_tracking_updated_at)
            : null;

        if ($currentTrackedAt && $trackedAt->lt($currentTrackedAt)) {
            return [
                'updated' => false,
                'message' => 'Preskočen je stariji tracking update.',
                'tracking' => $tracking,
            ];
        }

        $previousStatusCode = (string) ($order->shipping_tracking_status_code ?? '');
        $previousTrackingCode = trim((string) $order->tracking_code);
        $incomingStatusCode = trim((string) ($tracking['status_code'] ?? ''));
        $newStatusCode = $incomingStatusCode !== '' ? $incomingStatusCode : $previousStatusCode;
        $newStatus = $tracking['status'] ?? null;

        // Privremeni BOX NOW odgovor bez state/event podatka ne smije obrisati
        // posljednji poznati status pošiljke.
        if ($incomingStatusCode === '' && $previousStatusCode !== '') {
            $newStatus = $order->shipping_tracking_status;
        }

        $order->forceFill([
            'shipping_carrier' => BoxNowService::CARRIER,
            'shipping_parcel_id' => $tracking['parcel_id'] ?? $order->shipping_parcel_id,
            'tracking_code' => $tracking['tracking_code'] ?? $order->tracking_code,
            'shipping_tracking_url' => $tracking['tracking_url'] ?? $order->shipping_tracking_url,
            'shipping_tracking_status_code' => $newStatusCode ?: null,
            'shipping_tracking_status' => $newStatus,
            'shipping_tracking_updated_at' => $trackedAt,
            'shipping_tracking_attempted_at' => $trackedAt,
            'shipping_tracking_payload' => $tracking['payload'] ?? [],
            // Terminalni status i lokalna shipped oznaka moraju biti spremljeni
            // atomarno kako scheduler ne bi trajno preskočio pola updatea.
            'shipped' => ! empty($tracking['is_delivered']) ? true : (bool) $order->shipped,
        ])->save();

        $trackingCodeFirstAppeared = $previousTrackingCode === ''
            && trim((string) $order->tracking_code) !== '';

        if ($writeHistory && (
            ($incomingStatusCode !== '' && $incomingStatusCode !== $previousStatusCode)
            || $trackingCodeFirstAppeared
        )) {
            $this->storeHistory($order, $tracking);
        }

        return [
            'updated' => true,
            'message' => 'Tracking je osvježen: ' . ($tracking['status'] ?? 'status nije dostupan'),
            'tracking' => $tracking,
        ];
    }

    public function isBoxNowOrder(Order $order): bool
    {
        $carrier = strtolower(trim((string) $order->shipping_carrier));

        if ($carrier !== '') {
            return $carrier === BoxNowService::CARRIER;
        }

        $shipping = strtolower((string) $order->shipping_method . ' ' . (string) $order->shipping_code);

        return str_contains($shipping, 'boxnow') || str_contains($shipping, 'box now');
    }

    private function trackedAt($value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        try {
            return $value ? Carbon::parse($value) : now();
        } catch (\Throwable $exception) {
            return now();
        }
    }

    private function storeHistory(Order $order, array $tracking): void
    {
        $status = $tracking['status'] ?? 'status nije dostupan';
        $trackingCode = trim((string) ($tracking['tracking_code'] ?? ''));
        $trackingInfo = $trackingCode !== '' ? ' Broj pošiljke: ' . $trackingCode . '.' : '';

        OrderHistory::query()->create([
            'order_id' => $order->id,
            'user_id' => auth()->id() ?: 0,
            'status' => 0,
            'comment' => 'Tracking update (Box Now): ' . $status . $trackingInfo,
        ]);
    }
}
