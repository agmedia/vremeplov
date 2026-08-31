<?php

namespace App\Services\Shipping;

use App\Models\Back\Orders\Order;
use Illuminate\Support\Str;

class BoxNowOrderPolicy
{
    /** @var BoxNowSettingsService */
    private $settings;

    public function __construct(BoxNowSettingsService $settings)
    {
        $this->settings = $settings;
    }

    public function parcelId(Order $order): string
    {
        $parcelId = trim((string) $order->shipping_parcel_id);

        if ($parcelId !== '') {
            return $parcelId;
        }

        // tracking_code je zajedničko legacy polje i može sadržavati GLS broj.
        // Smije biti BOX NOW fallback samo uz eksplicitno spremljen carrier.
        if (Str::lower(trim((string) $order->shipping_carrier)) === BoxNowService::CARRIER) {
            return trim((string) $order->tracking_code);
        }

        return '';
    }

    public function hasShipment(Order $order): bool
    {
        return $this->parcelId($order) !== '';
    }

    public function canDispatch(Order $order): bool
    {
        $status = (int) $order->order_status_id;
        $paymentCode = Str::lower(trim((string) $order->payment_code));
        $allowed = array_values(array_filter(array_map('intval', [
            config('settings.order.status.paid'),
            config('settings.order.status.not_sent'),
        ])));

        // "pickup" je plaćanje kod osobnog preuzimanja, a ne unaprijed
        // plaćena BOX NOW narudžba.
        if ($paymentCode === 'pickup') {
            return false;
        }

        if ($paymentCode === 'cod') {
            $codStatuses = array_merge($allowed, [
                (int) config('settings.order.status.new'),
            ]);

            return (bool) $this->settings->get()['cod_enabled']
                && in_array($status, $codStatuses, true);
        }

        return in_array($status, $allowed, true);
    }

    public function dispatchBlockedReason(Order $order): ?string
    {
        if (Str::lower(trim((string) $order->payment_code)) === 'pickup') {
            return 'Plaćanje kod osobnog preuzimanja nije dopušteno za BOX NOW.';
        }

        return $this->canDispatch($order)
            ? null
            : 'Box Now pošiljku moguće je kreirati tek za plaćenu narudžbu, narudžbu označenu kao ne poslanu ili COD omogućen u BOX NOW postavkama.';
    }
}
