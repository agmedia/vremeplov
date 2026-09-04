<?php

namespace App\Services\Orders;

use App\Mail\OrderReceived;
use App\Mail\OrderSent;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderMailDelivery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class OrderConfirmationService
{
    private const CONFIRMABLE_STATUSES = [1, 2, 3, 4, 9, 10, 11];
    private const CUSTOMER = 'customer_confirmation';
    private const ADMIN = 'admin_notification';

    public function dispatchAfterResponse(Order $order): void
    {
        $orderId = (int) $order->id;
        $this->enqueue($order);

        dispatch(function () use ($orderId) {
            app(self::class)->sendOnce($orderId);
        })->afterResponse();
    }

    public function enqueue(Order $order): void
    {
        if (! Schema::hasTable('order_mail_deliveries') || ! $this->isEligible($order, false)) {
            return;
        }

        $recipients = [
            self::CUSTOMER => trim((string) $order->payment_email),
            self::ADMIN => trim((string) config('mail.admin')),
        ];

        foreach ($recipients as $type => $recipient) {
            if ($recipient === '') {
                Log::error('Order mail recipient is not configured.', [
                    'order_id' => $order->id,
                    'type' => $type,
                ]);
                continue;
            }

            OrderMailDelivery::query()->firstOrCreate(
                ['order_id' => $order->id, 'type' => $type],
                ['recipient' => $recipient, 'next_attempt_at' => now()]
            );
        }
    }

    public function sendOnce(int $orderId): bool
    {
        $order = Order::query()->find($orderId);

        if (! $order || ! $this->isEligible($order)) {
            return false;
        }

        if (! Schema::hasTable('order_mail_deliveries')) {
            return $this->sendLegacyPair($order);
        }

        $this->enqueue($order);
        $deliveries = OrderMailDelivery::query()
            ->where('order_id', $orderId)
            ->whereNull('sent_at')
            ->where(function ($query) {
                $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->get();

        $allSent = true;

        foreach ($deliveries as $delivery) {
            if (! $this->sendDelivery($delivery)) {
                $allSent = false;
            }
        }

        $pending = OrderMailDelivery::query()
            ->where('order_id', $orderId)
            ->whereNull('sent_at')
            ->exists();

        if (! $pending && OrderMailDelivery::query()->where('order_id', $orderId)->count() === 2) {
            Order::query()->whereKey($orderId)->whereNull('confirmation_sent_at')
                ->update(['confirmation_sent_at' => now()]);
        }

        return $allSent && ! $pending;
    }

    public function processPending(int $limit = 50): array
    {
        if (! Schema::hasTable('order_mail_deliveries')) {
            return ['sent' => 0, 'failed' => 0];
        }

        $orderIds = OrderMailDelivery::query()
            ->whereNull('sent_at')
            ->where(function ($query) {
                $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('order_id')
            ->unique();

        $sent = 0;
        $failed = 0;

        foreach ($orderIds as $orderId) {
            if ($this->sendOnce((int) $orderId)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return compact('sent', 'failed');
    }

    private function sendDelivery(OrderMailDelivery $delivery): bool
    {
        $lock = Cache::lock('order-mail-delivery:' . $delivery->id, 120);

        if (! $lock->get()) {
            return false;
        }

        try {
            $delivery->refresh();

            if ($delivery->sent_at !== null) {
                return true;
            }

            $order = Order::query()->find($delivery->order_id);

            if (! $order || ! $this->isEligible($order, false)) {
                return false;
            }

            $delivery->increment('attempts');

            if ($delivery->type === self::CUSTOMER) {
                Mail::to($delivery->recipient)->send(new OrderSent($order));
            } elseif ($delivery->type === self::ADMIN) {
                Mail::to($delivery->recipient)->send(new OrderReceived($order));
            } else {
                throw new \RuntimeException('Nepoznata vrsta maila narudžbe.');
            }

            $delivery->forceFill([
                'sent_at' => now(),
                'next_attempt_at' => null,
                'last_error' => null,
            ])->save();

            return true;
        } catch (\Throwable $exception) {
            $attempts = max(1, (int) $delivery->attempts);
            $delivery->forceFill([
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                'next_attempt_at' => now()->addMinutes(min(60, 2 ** min($attempts, 6))),
            ])->save();

            Log::error('Order mail delivery failed.', [
                'order_id' => $delivery->order_id,
                'type' => $delivery->type,
                'attempts' => $attempts,
                'error' => $exception->getMessage(),
            ]);

            return false;
        } finally {
            $lock->release();
        }
    }

    private function sendLegacyPair(Order $order): bool
    {
        $lock = Cache::lock('order-confirmation:' . $order->id, 120);

        if (! $lock->get()) {
            return false;
        }

        try {
            Mail::to(config('mail.admin'))->send(new OrderReceived($order));
            Mail::to($order->payment_email)->send(new OrderSent($order));
            $order->forceFill(['confirmation_sent_at' => now()])->save();

            return true;
        } catch (\Throwable $exception) {
            Log::error('Order confirmation mail failed.', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        } finally {
            $lock->release();
        }
    }

    private function isEligible(Order $order, bool $respectSentFlag = true): bool
    {
        return (! $respectSentFlag || $order->confirmation_sent_at === null)
            && in_array((int) $order->order_status_id, self::CONFIRMABLE_STATUSES, true)
            && $order->inventory_committed_at !== null
            && $order->inventory_released_at === null
            && ! $order->inventory_allocation_error
            && ! $order->payment_review_error;
    }
}
