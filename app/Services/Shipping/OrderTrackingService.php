<?php

namespace App\Services\Shipping;

use App\Mail\ShippingTrackingAvailable;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class OrderTrackingService
{
    public const TRACKING_EMAIL_HISTORY_COMMENT = 'Kupcu poslan email s podacima za praćenje pošiljke.';

    private const REFRESH_LOCK_SECONDS = 90;

    /** @var GlsTrackingService */
    private $gls;

    /** @var BoxNowService */
    private $boxNow;

    public function __construct(BoxNowService $boxNow, ?GlsTrackingService $gls = null)
    {
        $this->gls = $gls ?: new GlsTrackingService();
        $this->boxNow = $boxNow;
    }

    public function refresh(Order $order): array
    {
        $carrier = $this->resolveCarrier($order);

        if ($carrier === GlsTrackingService::CARRIER) {
            return $this->refreshGls($order);
        }

        if ($carrier === BoxNowService::CARRIER) {
            return $this->refreshBoxNow($order);
        }

        throw new RuntimeException('Praćenje nije podržano za ovaj način dostave.');
    }

    public function refreshGls(Order $order): array
    {
        if ($this->resolveCarrier($order) !== GlsTrackingService::CARRIER) {
            throw new RuntimeException('Narudžba nema odabranu GLS dostavu.');
        }

        return $this->refreshWithLock($order, GlsTrackingService::CARRIER, function (Order $freshOrder) {
            return $this->gls->trackOrder($freshOrder);
        });
    }

    public function refreshBoxNow(Order $order): array
    {
        if (! $this->isBoxNowOrder($order)) {
            throw new RuntimeException('Narudžba nema odabranu Box Now dostavu.');
        }

        return $this->refreshWithLock($order, BoxNowService::CARRIER, function (Order $freshOrder) {
            return $this->boxNow->track($freshOrder);
        });
    }

    private function refreshWithLock(Order $order, string $carrier, callable $resolver): array
    {
        $carrierLabel = $this->carrierLabel($carrier);

        $lock = Cache::lock(
            $carrier . '-tracking-refresh:' . $order->id,
            self::REFRESH_LOCK_SECONDS
        );

        if (! $lock->get()) {
            return [
                'updated' => false,
                'message' => 'Osvježavanje ' . $carrierLabel . ' statusa za ovu narudžbu već je u tijeku.',
                'tracking' => [],
            ];
        }

        try {
            if ($order->exists) {
                $order->refresh();
            }

            if ($this->resolveCarrier($order) !== $carrier) {
                throw new RuntimeException('Narudžba više nema odabranu ' . $carrierLabel . ' dostavu.');
            }

            return $this->apply($order, $resolver($order));
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
        $hadCustomerTrackingIdentifier = $this->hasCustomerTrackingIdentifier($order);

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
            'shipping_carrier' => $tracking['carrier'] ?? $this->resolveCarrier($order),
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

        $this->sendTrackingAvailableMail($order, $hadCustomerTrackingIdentifier);

        return [
            'updated' => true,
            'message' => 'Tracking je osvježen: ' . ($tracking['status'] ?? 'status nije dostupan'),
            'tracking' => $tracking,
        ];
    }

    public function isBoxNowOrder(Order $order): bool
    {
        return $this->resolveCarrier($order) === BoxNowService::CARRIER;
    }

    public function resolveCarrier(Order $order): ?string
    {
        $carrier = Str::lower(trim((string) $order->shipping_carrier));

        if ($carrier !== '') {
            return in_array($carrier, [GlsTrackingService::CARRIER, BoxNowService::CARRIER], true)
                ? $carrier
                : null;
        }

        $shipping = Str::lower((string) $order->shipping_method . ' ' . (string) $order->shipping_code);

        if (Str::contains($shipping, ['boxnow', 'box now'])) {
            return BoxNowService::CARRIER;
        }

        return Str::contains($shipping, 'gls') ? GlsTrackingService::CARRIER : null;
    }

    public function carrierLabel(?string $carrier): string
    {
        return [
            GlsTrackingService::CARRIER => 'GLS',
            BoxNowService::CARRIER => 'Box Now',
        ][$carrier] ?? 'Dostava';
    }

    public function trackingUrlForOrder(Order $order): ?string
    {
        if (filled($order->shipping_tracking_url)) {
            return (string) $order->shipping_tracking_url;
        }

        $identifier = trim((string) ($order->tracking_code ?: $order->shipping_parcel_id));

        if ($identifier === '') {
            return null;
        }

        if ($this->resolveCarrier($order) === BoxNowService::CARRIER) {
            return $this->boxNow->trackingUrl($identifier);
        }

        if ($this->resolveCarrier($order) === GlsTrackingService::CARRIER && filled($order->tracking_code)) {
            return $this->gls->trackingUrl((string) $order->tracking_code);
        }

        return null;
    }

    public function trackingEmailSentAt(Order $order): ?Carbon
    {
        if (Schema::hasColumn('orders', 'shipping_tracking_email_sent_at') && $order->shipping_tracking_email_sent_at) {
            return Carbon::make($order->shipping_tracking_email_sent_at);
        }

        $historyCreatedAt = OrderHistory::query()
            ->where('order_id', $order->id)
            ->where('comment', 'like', self::TRACKING_EMAIL_HISTORY_COMMENT . '%')
            ->latest('created_at')
            ->value('created_at');

        return $historyCreatedAt ? Carbon::make($historyCreatedAt) : null;
    }

    public function sendTrackingAvailableMailManually(Order $order): array
    {
        if ($this->resolveCarrier($order) !== GlsTrackingService::CARRIER) {
            return [
                'sent' => false,
                'error' => 'Tracking email trenutačno je dostupan samo za GLS dostavu.',
            ];
        }

        if (! $this->hasCustomerTrackingIdentifier($order)) {
            return [
                'sent' => false,
                'error' => 'GLS tracking broj nije upisan.',
            ];
        }

        if ($this->customerEmail($order) === '') {
            return [
                'sent' => false,
                'error' => 'Narudžba nema e-mail adresu kupca.',
            ];
        }

        $sentAt = $this->trackingEmailSentAt($order);

        if ($sentAt) {
            return [
                'sent' => false,
                'message' => 'Tracking email je već poslan kupcu ' . $sentAt->format('d.m.Y H:i') . '.',
            ];
        }

        $this->sendTrackingAvailableMail($order, false, true);

        return [
            'sent' => true,
            'message' => 'GLS tracking email je poslan kupcu.',
        ];
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
        $carrier = $this->carrierLabel($tracking['carrier'] ?? $this->resolveCarrier($order));
        $status = $tracking['status'] ?? 'status nije dostupan';
        $trackingCode = trim((string) ($tracking['tracking_code'] ?? ''));
        $trackingInfo = $trackingCode !== '' ? ' Broj pošiljke: ' . $trackingCode . '.' : '';

        OrderHistory::query()->create([
            'order_id' => $order->id,
            'user_id' => auth()->id() ?: 0,
            'status' => 0,
            'comment' => 'Tracking update (' . $carrier . '): ' . $status . $trackingInfo,
        ]);
    }

    private function sendTrackingAvailableMail(Order $order, bool $hadCustomerTrackingIdentifier, bool $throwOnFailure = false): bool
    {
        if ($this->resolveCarrier($order) !== GlsTrackingService::CARRIER
            || $hadCustomerTrackingIdentifier
            || ! $this->hasCustomerTrackingIdentifier($order)
            || $this->trackingEmailSentAt($order)
        ) {
            return false;
        }

        $email = $this->customerEmail($order);

        if ($email === '') {
            return false;
        }

        try {
            Mail::to($email)->send(new ShippingTrackingAvailable(
                $order->fresh(['products', 'totals']) ?: $order
            ));

            if (Schema::hasColumn('orders', 'shipping_tracking_email_sent_at')) {
                $order->forceFill([
                    'shipping_tracking_email_sent_at' => now(),
                ])->save();
            }

            OrderHistory::query()->create([
                'order_id' => $order->id,
                'user_id' => auth()->id() ?: 0,
                'status' => 0,
                'comment' => self::TRACKING_EMAIL_HISTORY_COMMENT
                    . ' Broj pošiljke: ' . $this->customerTrackingIdentifier($order) . '.',
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('GLS tracking email failed.', [
                'order_id' => $order->id,
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);

            if ($throwOnFailure) {
                throw $exception;
            }
        }

        return false;
    }

    private function hasCustomerTrackingIdentifier(Order $order): bool
    {
        return $this->customerTrackingIdentifier($order) !== '';
    }

    private function customerTrackingIdentifier(Order $order): string
    {
        return trim((string) $order->tracking_code);
    }

    private function customerEmail(Order $order): string
    {
        return trim((string) ($order->payment_email ?: $order->shipping_email));
    }
}
