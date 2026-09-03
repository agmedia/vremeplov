<?php

namespace App\Services\Orders;

use App\Mail\OrderReceived;
use App\Mail\OrderSent;
use App\Models\Back\Orders\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderConfirmationService
{
    private const CONFIRMABLE_STATUSES = [1, 2, 3, 4, 9, 10, 11];

    public function dispatchAfterResponse(Order $order): void
    {
        $orderId = (int) $order->id;

        dispatch(function () use ($orderId) {
            app(self::class)->sendOnce($orderId);
        })->afterResponse();
    }

    public function sendOnce(int $orderId): bool
    {
        $lock = Cache::lock('order-confirmation:' . $orderId, 120);

        if (! $lock->get()) {
            return false;
        }

        try {
            $order = Order::query()->find($orderId);

            if (! $order
                || $order->confirmation_sent_at !== null
                || ! in_array((int) $order->order_status_id, self::CONFIRMABLE_STATUSES, true)
                || $order->inventory_committed_at === null
                || $order->inventory_released_at !== null
                || $order->inventory_allocation_error
                || $order->payment_review_error) {
                return false;
            }

            Mail::to(config('mail.admin'))->send(new OrderReceived($order));
            Mail::to($order->payment_email)->send(new OrderSent($order));

            Order::query()
                ->whereKey($orderId)
                ->whereNull('confirmation_sent_at')
                ->update(['confirmation_sent_at' => now()]);

            return true;
        } catch (\Throwable $exception) {
            Log::error('Order confirmation mail failed.', [
                'order_id' => $orderId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        } finally {
            optional($lock)->release();
        }
    }
}
