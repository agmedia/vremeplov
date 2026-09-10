<?php

$eligibleStatuses = array_values(array_filter(array_map(
    'intval',
    explode(',', (string) env('REVIEW_REQUEST_ELIGIBLE_STATUSES', '4,9,10'))
)));
$minimumIntervalSeconds = max(120, (int) env('REVIEW_MIN_INTERVAL_SECONDS', 120));
$backfillIntervalOptions = array_values(array_unique(array_filter(
    [$minimumIntervalSeconds, 120, 300],
    fn (int $seconds) => $seconds >= $minimumIntervalSeconds
)));
sort($backfillIntervalOptions);

if ($backfillIntervalOptions === []) {
    $backfillIntervalOptions = [$minimumIntervalSeconds];
}

return [
    // Slanje ostaje isključeno dok se migracija i dry-run ne provjere na produkciji.
    'request_emails_enabled' => env('REVIEW_REQUEST_EMAILS_ENABLED', false),
    'request_delay_days' => max(1, (int) env('REVIEW_REQUEST_DELAY_DAYS', 10)),
    // Initial catch-up and all later sends are restricted to recently created orders.
    'request_order_lookback_days' => max(1, (int) env('REVIEW_REQUEST_ORDER_LOOKBACK_DAYS', 60)),
    'request_daily_limit' => max(1, min((int) env('REVIEW_REQUEST_DAILY_LIMIT', 100), 1000)),
    'request_max_attempts' => max(1, (int) env('REVIEW_REQUEST_MAX_ATTEMPTS', 3)),
    'request_link_days' => max(1, (int) env('REVIEW_REQUEST_LINK_DAYS', 180)),
    // Shared-hosting mail is capped at 100/hour. Reserve capacity for order and wishlist mail.
    'minimum_interval_seconds' => $minimumIntervalSeconds,
    'eligible_status_ids' => $eligibleStatuses ?: [4, 9, 10],
    'backfill_max_orders' => max(1, (int) env('REVIEW_BACKFILL_MAX_ORDERS', 5000)),
    'backfill_default_interval_seconds' => max(
        $minimumIntervalSeconds,
        (int) env('REVIEW_BACKFILL_INTERVAL_SECONDS', $minimumIntervalSeconds)
    ),
    'backfill_interval_options' => $backfillIntervalOptions,
    'backfill_run_seconds' => max(1, min((int) env('REVIEW_BACKFILL_RUN_SECONDS', 50), 58)),
    'backfill_admin_email' => env('REVIEW_BACKFILL_ADMIN_EMAIL', 'tomislav@agmedia.hr'),
];
