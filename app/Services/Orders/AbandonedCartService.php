<?php

namespace App\Services\Orders;

use App\Mail\AbandonedCartReminderMail;
use App\Models\AbandonedCartReminder;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderHistory;
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

    public function candidates(int $sequence, int $limit, bool $ignoreEnabled = false): Collection
    {
        if ((! $ignoreEnabled && ! config('abandoned_cart.enabled')) || ! $this->isAvailable()) {
            return collect();
        }

        $delay = config('abandoned_cart.delays_minutes.' . $sequence);
        if ($delay === null) {
            return collect();
        }

        $query = Order::query()
            ->where('order_status_id', (int) config('settings.order.status.unfinished', 8))
            ->where('created_at', '>=', $this->candidateCutoff($sequence))
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

    public function adminState(Order $order): array
    {
        if (! $this->isAvailable()) {
            return ['available' => false, 'complete' => false, 'error' => 'Evidencija podsjetnika nije instalirana.'];
        }

        $sent = $this->sentReminders($order);
        $nextSequence = null;
        for ($sequence = 1; $sequence <= (int) config('abandoned_cart.max_reminders', 2); $sequence++) {
            if (! $sent->has($sequence)) {
                $nextSequence = $sequence;
                break;
            }
        }

        $error = $this->eligibilityError($order);

        return [
            'available' => $error === null && $nextSequence !== null,
            'complete' => $nextSequence === null,
            'next_sequence' => $nextSequence,
            'next_scheduled_at' => $nextSequence ? $this->scheduledFor($order, $nextSequence) : null,
            'first' => $sent->get(1),
            'second' => $sent->get(2),
            'error' => $error,
        ];
    }

    public function send(
        Order $order,
        int $sequence,
        string $source = AbandonedCartReminder::SOURCE_AUTOMATIC
    ): bool
    {
        if (! in_array($source, [AbandonedCartReminder::SOURCE_AUTOMATIC, AbandonedCartReminder::SOURCE_MANUAL], true)) {
            throw new RuntimeException('Nepoznat izvor podsjetnika.');
        }

        if (! config('abandoned_cart.enabled')) {
            throw new RuntimeException('Podsjetnici za nedovršenu kupnju su isključeni.');
        }

        $order->loadMissing(['products.product', 'totals', 'abandonedCartReminders']);

        if ($error = $this->eligibilityError($order)) {
            throw new RuntimeException($error);
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
                    'source' => $source,
                    'sent_by' => $source === AbandonedCartReminder::SOURCE_MANUAL ? auth()->id() : null,
                ]
            );

            if ($reminder->sent_at) {
                return true;
            }

            if ($source === AbandonedCartReminder::SOURCE_AUTOMATIC
                && $reminder->next_attempt_at
                && $reminder->next_attempt_at->isFuture()) {
                return false;
            }

            $reminder->forceFill([
                'source' => $source,
                'sent_by' => $source === AbandonedCartReminder::SOURCE_MANUAL ? auth()->id() : null,
                'recipient_email' => $email,
            ])->save();
            $reminder->increment('attempts');
            $url = URL::temporarySignedRoute(
                'abandoned-cart.restore',
                now()->addDays((int) config('abandoned_cart.recovery_link_days', 7)),
                ['order' => $order->id]
            );

            Mail::to($email)->send(new AbandonedCartReminderMail($order, $url, $sequence));
            $reminder->forceFill(['sent_at' => now(), 'next_attempt_at' => null, 'last_error' => null])->save();
            if (Schema::hasTable('order_history')) {
                OrderHistory::query()->create([
                    'order_id' => $order->id,
                    'user_id' => auth()->id() ?: 0,
                    'status' => (int) $order->order_status_id,
                    'comment' => sprintf(
                        '%d. podsjetnik za nedovršenu narudžbu poslan je %s na %s.',
                        $sequence,
                        $source === AbandonedCartReminder::SOURCE_MANUAL ? 'ručno' : 'automatski',
                        $email
                    ),
                ]);
            }

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

    public function scheduledFor(Order $order, int $sequence): Carbon
    {
        $delay = config('abandoned_cart.delays_minutes.' . $sequence);
        if ($delay === null) {
            throw new RuntimeException('Nepoznat redni broj podsjetnika.');
        }

        return Carbon::parse($order->created_at)->addMinutes((int) $delay);
    }

    private function sentReminders(Order $order): Collection
    {
        $reminders = $order->relationLoaded('abandonedCartReminders')
            ? $order->abandonedCartReminders
            : $order->abandonedCartReminders()->get();

        return $reminders->whereNotNull('sent_at')->keyBy('sequence');
    }

    private function eligibilityError(Order $order): ?string
    {
        if (! config('abandoned_cart.enabled')) {
            return 'Podsjetnici za nedovršene narudžbe su isključeni.';
        }
        if (! $this->canRecover($order)) {
            return 'Podsjetnik se može poslati samo za noviju nedovršenu narudžbu.';
        }
        if (! filter_var(trim((string) $order->payment_email), FILTER_VALIDATE_EMAIL)) {
            return 'Narudžba nema valjanu e-mail adresu kupca.';
        }

        $productsCount = $order->relationLoaded('products')
            ? $order->products->count()
            : (int) ($order->products_count ?? $order->products()->count());
        if ($productsCount < 1) {
            return 'Narudžba nema artikala za podsjetnik.';
        }

        $email = mb_strtolower(trim((string) $order->payment_email));
        $hasNewerCompleted = Order::query()
            ->whereIn('order_status_id', self::COMPLETED_STATUSES)
            ->whereRaw('LOWER(TRIM(payment_email)) = ?', [$email])
            ->where('created_at', '>', $order->created_at)
            ->exists();

        return $hasNewerCompleted ? 'Kupac je u međuvremenu dovršio noviju narudžbu.' : null;
    }

    private function startsAt(): Carbon
    {
        return Carbon::parse((string) config('abandoned_cart.starts_at'), config('app.timezone'));
    }

    private function candidateCutoff(int $sequence): Carbon
    {
        $sequenceDelayHours = (int) ceil(((int) config('abandoned_cart.delays_minutes.' . $sequence, 0)) / 60);
        $lookbackHours = max(
            (int) config('abandoned_cart.lookback_hours', 24),
            $sequenceDelayHours + 2
        );
        $rollingCutoff = now()->subHours($lookbackHours);

        return $this->startsAt()->greaterThan($rollingCutoff)
            ? $this->startsAt()
            : $rollingCutoff;
    }
}
