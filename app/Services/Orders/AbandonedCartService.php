<?php

namespace App\Services\Orders;

use App\Mail\AbandonedCartReminderMail;
use App\Models\AbandonedCartReminder;
use App\Models\Back\Orders\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class AbandonedCartService
{
    private const COMPLETED_STATUSES = [1, 2, 3, 4, 9, 10, 11];

    public function isAvailable(): bool
    {
        return Schema::hasTable('abandoned_cart_reminders');
    }

    public function candidates(int $sequence, int $limit): Collection
    {
        if (! config('abandoned_cart.enabled') || ! $this->isAvailable()) {
            return collect();
        }

        $delay = config('abandoned_cart.delays_minutes.' . $sequence);
        if ($delay === null) {
            return collect();
        }

        $query = Order::query()
            ->where('order_status_id', (int) config('settings.order.status.unfinished', 8))
            ->where('created_at', '>=', $this->startsAt())
            ->where('created_at', '<=', now()->subMinutes((int) $delay))
            ->whereNotNull('payment_email')
            ->whereRaw("TRIM(payment_email) <> ''")
            ->whereHas('products')
            ->whereDoesntHave('abandonedCartReminders', function (Builder $reminders) use ($sequence) {
                $reminders->where('sequence', $sequence)->whereNotNull('sent_at');
            })
            ->where(function (Builder $retryable) use ($sequence) {
                $retryable
                    ->whereDoesntHave('abandonedCartReminders', function (Builder $reminders) use ($sequence) {
                        $reminders->where('sequence', $sequence);
                    })
                    ->orWhereHas('abandonedCartReminders', function (Builder $reminders) use ($sequence) {
                        $reminders->where('sequence', $sequence)
                            ->whereNull('sent_at')
                            ->where(function (Builder $due) {
                                $due->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                            });
                    });
            })
            ->whereNotExists(function ($newer) {
                $newer->selectRaw('1')->from('orders as newer_orders')
                    ->whereIn('newer_orders.order_status_id', self::COMPLETED_STATUSES)
                    ->whereRaw('LOWER(TRIM(newer_orders.payment_email)) = LOWER(TRIM(orders.payment_email))')
                    ->whereColumn('newer_orders.created_at', '>', 'orders.created_at');
            });

        if ($sequence > 1) {
            $query->whereHas('abandonedCartReminders', function (Builder $reminders) use ($sequence) {
                $reminders->where('sequence', $sequence - 1)->whereNotNull('sent_at');
            });
        }

        return $query->with(['products.product', 'totals', 'abandonedCartReminders'])
            ->oldest('created_at')->limit(max(1, $limit))->get();
    }

    public function send(Order $order, int $sequence): bool
    {
        if (! config('abandoned_cart.enabled')) {
            throw new RuntimeException('Podsjetnici za nedovršenu kupnju su isključeni.');
        }

        if (! $this->canRecover($order)) {
            throw new RuntimeException('Narudžbu više nije moguće obnoviti.');
        }

        if (! in_array($sequence, [1, 2], true)) {
            throw new RuntimeException('Nepoznat redni broj podsjetnika.');
        }

        $email = mb_strtolower(trim((string) $order->payment_email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Narudžba nema valjanu e-mail adresu.');
        }

        $lock = Cache::lock("abandoned-cart:{$order->id}:{$sequence}", 120);
        if (! $lock->get()) {
            return false;
        }

        try {
            $reminder = AbandonedCartReminder::query()->firstOrCreate(
                ['order_id' => $order->id, 'sequence' => $sequence],
                [
                    'scheduled_for' => Carbon::parse($order->created_at)
                        ->addMinutes((int) config('abandoned_cart.delays_minutes.' . $sequence)),
                    'next_attempt_at' => now(),
                    'recipient_email' => $email,
                ]
            );

            if ($reminder->sent_at) {
                return true;
            }

            if ($reminder->next_attempt_at && $reminder->next_attempt_at->isFuture()) {
                return false;
            }

            $reminder->increment('attempts');
            $url = URL::temporarySignedRoute(
                'abandoned-cart.restore',
                now()->addDays((int) config('abandoned_cart.recovery_link_days', 7)),
                ['order' => $order->id]
            );

            Mail::to($email)->send(new AbandonedCartReminderMail($order, $url, $sequence));
            $reminder->forceFill(['sent_at' => now(), 'next_attempt_at' => null, 'last_error' => null])->save();

            return true;
        } catch (\Throwable $exception) {
            if (isset($reminder)) {
                $reminder->forceFill([
                    'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                    'next_attempt_at' => now()->addMinutes(min(60, 2 ** min((int) $reminder->attempts, 6))),
                ])->save();
            }

            Log::warning('Abandoned checkout reminder failed.', [
                'order_id' => $order->id,
                'sequence' => $sequence,
                'error' => $exception->getMessage(),
            ]);

            return false;
        } finally {
            $lock->release();
        }
    }

    public function canRecover(Order $order): bool
    {
        return (int) $order->order_status_id === (int) config('settings.order.status.unfinished', 8)
            && $order->created_at
            && Carbon::parse($order->created_at)->gte($this->startsAt());
    }

    private function startsAt(): Carbon
    {
        return Carbon::parse((string) config('abandoned_cart.starts_at'), config('app.timezone'));
    }
}
