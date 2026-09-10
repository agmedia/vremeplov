<?php

namespace App\Services\Payments;

use App\Models\Back\Orders\Order;
use App\Models\Front\Checkout\Payment\PayPalStandard;
use App\Services\Orders\OrderConfirmationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PayPalReconciliationService
{
    /** @var PayPalReportingClient */
    private $client;

    public function __construct(PayPalReportingClient $client)
    {
        $this->client = $client;
    }

    public function reconcile(bool $dryRun = false, ?int $limit = null): array
    {
        $stats = ['candidates' => 0, 'matched' => 0, 'confirmed' => 0, 'review' => 0, 'errors' => 0];

        if (! config('paypal.reconciliation.enabled', true)) {
            return $stats;
        }

        $limit = max(1, $limit ?: (int) config('paypal.reconciliation.batch_size', 200));
        $delay = max(5, (int) config('paypal.reconciliation.delay_minutes', 15));
        $lookback = min(30, max(1, (int) config('paypal.reconciliation.lookback_days', 30)));
        $reviewStatuses = [
            (int) config('settings.order.status.unfinished'),
            (int) config('settings.order.status.canceled'),
            (int) config('settings.order.status.declined'),
            (int) config('settings.order.status.returned'),
            (int) config('settings.order.status.refund'),
            (int) config('settings.order.status.blacklist'),
        ];

        $orders = Order::query()
            ->whereRaw('LOWER(payment_code) = ?', ['paypal'])
            ->where('payment_attempt_provider', 'paypal')
            ->whereNotNull('payment_attempt_reference')
            ->whereNotNull('payment_expected_amount_minor')
            ->whereNotNull('payment_expected_currency')
            ->whereNotNull('payment_attempt_environment')
            ->whereNotNull('payment_attempt_started_at')
            ->where('payment_attempt_started_at', '>=', now()->subDays($lookback))
            ->where('payment_attempt_started_at', '<=', now()->subMinutes($delay))
            ->whereIn('order_status_id', array_values(array_unique($reviewStatuses)))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('order_transactions as paypal_transactions')
                    ->whereColumn('paypal_transactions.order_id', 'orders.id')
                    ->where('paypal_transactions.payment_partner', 'PayPal')
                    ->where('paypal_transactions.provider_event', 'paypal_completed')
                    ->where('paypal_transactions.success', 1);
            })
            // Prefer recent payments if an unusual backlog ever exceeds the cap.
            ->latest('payment_attempt_started_at')
            ->limit($limit)
            ->get();

        $stats['candidates'] = $orders->count();

        foreach ($orders->groupBy('payment_attempt_environment') as $environment => $group) {
            if (! in_array($environment, ['test', 'live'], true)) {
                $stats['errors'] += $group->count();
                continue;
            }

            try {
                $start = Carbon::parse($group->min('payment_attempt_started_at'))->subMinutes(5);
                $report = $this->client->search($environment, $start, now());
                $byCustom = collect($report['transactions'])->groupBy(function ($detail) {
                    return trim((string) data_get($detail, 'transaction_info.custom_field'));
                });

                foreach ($group as $order) {
                    $details = $byCustom->get((string) $order->payment_attempt_reference, collect())
                        ->filter(function ($detail) {
                            return data_get($detail, 'transaction_info.transaction_status') === 'S';
                        })
                        ->unique(function ($detail) {
                            return (string) data_get($detail, 'transaction_info.transaction_id');
                        })
                        ->values();

                    if ($details->isEmpty()) {
                        continue;
                    }

                    if ($details->count() !== 1) {
                        $stats['review']++;
                        Log::critical('Multiple successful PayPal transactions share one payment attempt.', [
                            'order_id' => $order->id,
                            'transaction_count' => $details->count(),
                        ]);
                        continue;
                    }

                    $processor = new PayPalStandard($order);
                    $error = $processor->reportingValidationError(
                        $order,
                        (array) data_get($details->first(), 'transaction_info', [])
                    );

                    if ($error !== null) {
                        $stats['review']++;
                        Log::critical('PayPal Reporting transaction requires manual review.', [
                            'order_id' => $order->id,
                            'error' => $error,
                        ]);
                        continue;
                    }

                    $stats['matched']++;
                    if ($dryRun) {
                        continue;
                    }

                    $result = $processor->handleReportingTransaction(
                        $order,
                        (array) data_get($details->first(), 'transaction_info', [])
                    );

                    if (! $result['accepted']) {
                        $stats['errors']++;
                        continue;
                    }

                    $stats['confirmed']++;
                    if (! empty($result['should_notify'])) {
                        app(OrderConfirmationService::class)->enqueue($order->fresh());
                    }
                }
            } catch (\Throwable $exception) {
                $stats['errors'] += $group->count();
                Log::error('PayPal reconciliation failed closed.', [
                    'environment' => $environment,
                    'orders' => $group->pluck('id')->all(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $stats;
    }
}
