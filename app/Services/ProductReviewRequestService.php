<?php

namespace App\Services;

use App\Mail\ProductReviewRequestMail;
use App\Models\Back\Orders\Order;
use App\Models\ProductReviewInvitation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ProductReviewRequestService
{
    public const STATUS_SENT = 'sent';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    public function eligibleOrders(Carbon $from, Carbon $to): Builder
    {
        $maxAttempts = (int) config('reviews.request_max_attempts', 3);

        $eligible = Order::query()
            ->select(['orders.id', 'orders.payment_email'])
            ->whereIn('orders.order_status_id', Order::reviewEligibleStatusIds())
            ->whereNotNull('orders.payment_email')
            ->whereRaw("TRIM(orders.payment_email) <> ''")
            ->whereNotExists(function ($invitations) {
                $invitations->select(DB::raw(1))
                    ->from('product_review_invitations')
                    ->whereNotNull('product_review_invitations.sent_at')
                    ->whereRaw(
                        'product_review_invitations.recipient_email_normalized = LOWER(TRIM(orders.payment_email))'
                    );
            })
            ->where(function ($query) use ($from, $to) {
                $query->whereExists(function ($history) use ($from, $to) {
                    $history->select(DB::raw(1))
                        ->from('order_history')
                        ->whereColumn('order_history.order_id', 'orders.id')
                        ->where('order_history.status', (int) config('settings.order.status.send'))
                        ->whereBetween('order_history.created_at', [$from, $to]);
                })->orWhere(function ($fallback) use ($from, $to) {
                    $fallback->whereNotExists(function ($history) {
                        $history->select(DB::raw(1))
                            ->from('order_history')
                            ->whereColumn('order_history.order_id', 'orders.id')
                            ->where('order_history.status', (int) config('settings.order.status.send'));
                    })->whereBetween('orders.created_at', [$from, $to]);
                });
            })
            ->whereExists(function ($items) {
                $items->select(DB::raw(1))
                    ->from('order_products')
                    ->join('products', 'products.id', '=', 'order_products.product_id')
                    ->whereColumn('order_products.order_id', 'orders.id')
                    ->where('order_products.product_id', '>', 0)
                    ->whereNotExists(function ($reviews) {
                        $reviews->select(DB::raw(1))
                            ->from('reviews')
                            ->whereColumn('reviews.order_id', 'orders.id')
                            ->whereColumn('reviews.product_id', 'order_products.product_id');
                    });
            })
            ->where(function ($query) use ($maxAttempts) {
                $query->whereNotExists(function ($invitations) {
                    $invitations->select(DB::raw(1))
                        ->from('product_review_invitations')
                        ->whereColumn('product_review_invitations.order_id', 'orders.id');
                })->orWhereExists(function ($invitations) use ($maxAttempts) {
                    $invitations->select(DB::raw(1))
                        ->from('product_review_invitations')
                        ->whereColumn('product_review_invitations.order_id', 'orders.id')
                        ->whereNull('product_review_invitations.sent_at')
                        ->where('product_review_invitations.attempts', '<', $maxAttempts);
                });
            });

        $uniqueEmailCandidates = DB::query()
            ->fromSub($eligible->toBase(), 'eligible_orders')
            ->selectRaw('MIN(eligible_orders.id) AS order_id')
            ->groupByRaw('LOWER(TRIM(eligible_orders.payment_email))');

        return Order::query()
            ->joinSub($uniqueEmailCandidates, 'unique_email_candidates', function ($join) {
                $join->on('unique_email_candidates.order_id', '=', 'orders.id');
            })
            ->select('orders.*')
            ->addSelect([
                'sent_status_at' => DB::table('order_history')
                    ->selectRaw('MIN(created_at)')
                    ->whereColumn('order_history.order_id', 'orders.id')
                    ->where('order_history.status', (int) config('settings.order.status.send')),
            ])
            ->orderByRaw('COALESCE(sent_status_at, orders.created_at) ASC');
    }

    public function eligibleAt(Order $order): Carbon
    {
        return Carbon::parse($order->sent_status_at ?: $order->created_at);
    }

    /**
     * @return array{status:string, message:?string, attempts:int}
     */
    public function send(Order $order): array
    {
        $email = mb_strtolower(trim((string) $order->payment_email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->result(self::STATUS_SKIPPED, 'E-mail adresa nije valjana.');
        }

        $lock = Cache::lock('product-review-request-email:' . hash('sha256', $email), 300);
        if (! $lock->get()) {
            return $this->result(self::STATUS_SKIPPED, 'Slanje na ovu e-mail adresu već je u tijeku.');
        }

        try {
            return $this->sendToEmail($order, $email);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{status:string, message:?string, attempts:int}
     */
    private function sendToEmail(Order $order, string $email): array
    {
        if (ProductReviewInvitation::query()
            ->where('recipient_email_normalized', $email)
            ->whereNotNull('sent_at')
            ->exists()) {
            return $this->result(self::STATUS_SKIPPED, 'Poziv na ovu e-mail adresu već je poslan.');
        }

        if (! in_array((int) $order->order_status_id, Order::reviewEligibleStatusIds(), true)) {
            return $this->result(self::STATUS_SKIPPED, 'Status narudžbe više nije kvalificiran.');
        }

        $hasReviewableItem = DB::table('order_products')
            ->join('products', 'products.id', '=', 'order_products.product_id')
            ->where('order_products.order_id', $order->id)
            ->where('order_products.product_id', '>', 0)
            ->whereNotExists(function ($reviews) use ($order) {
                $reviews->select(DB::raw(1))
                    ->from('reviews')
                    ->where('reviews.order_id', $order->id)
                    ->whereColumn('reviews.product_id', 'order_products.product_id');
            })
            ->exists();

        if (! $hasReviewableItem) {
            return $this->result(self::STATUS_SKIPPED, 'Narudžba nema artikala dostupnih za recenziju.');
        }

        $plainToken = Str::random(64);
        $invitation = ProductReviewInvitation::query()->firstOrNew(['order_id' => $order->id]);
        if ($invitation->sent_at) {
            return $this->result(self::STATUS_SKIPPED, 'Poziv za ovu narudžbu već je poslan.', (int) $invitation->attempts);
        }

        $maxAttempts = (int) config('reviews.request_max_attempts', 3);
        if ((int) $invitation->attempts >= $maxAttempts) {
            return $this->result(self::STATUS_SKIPPED, 'Dosegnut je najveći broj pokušaja slanja.', (int) $invitation->attempts);
        }

        $name = trim((string) $order->payment_fname . ' ' . (string) $order->payment_lname);
        $invitation->forceFill([
            'token_hash' => ProductReviewInvitation::hashToken($plainToken),
            'recipient_email' => $email,
            'recipient_email_normalized' => $email,
            'recipient_name' => $name ?: 'Kupac',
            'eligible_at' => $this->eligibleAt($order),
            'attempts' => ((int) $invitation->attempts) + 1,
            'last_attempt_at' => now(),
            'last_error' => null,
        ])->save();

        $reviewUrl = URL::temporarySignedRoute(
            'product-review-invitations.show',
            now()->addDays((int) config('reviews.request_link_days', 180)),
            ['token' => $plainToken]
        );

        try {
            Mail::to($email)->send(new ProductReviewRequestMail($invitation, $reviewUrl));

            $invitation->forceFill([
                'sent_at' => now(),
                'last_error' => null,
            ])->save();

            return $this->result(self::STATUS_SENT, null, (int) $invitation->attempts);
        } catch (\Throwable $exception) {
            $message = Str::limit($exception->getMessage(), 5000, '');
            $invitation->forceFill(['last_error' => $message])->save();

            Log::warning('Product review request mail failed.', [
                'order_id' => $order->id,
                'invitation_id' => $invitation->id,
                'attempt' => $invitation->attempts,
                'error' => $exception->getMessage(),
            ]);

            return $this->result(self::STATUS_FAILED, $message, (int) $invitation->attempts);
        }
    }

    /**
     * @return array{status:string, message:?string, attempts:int}
     */
    private function result(string $status, ?string $message, int $attempts = 0): array
    {
        return compact('status', 'message', 'attempts');
    }
}
