<?php

$eligibleStatuses = array_values(array_filter(array_map(
    'intval',
    explode(',', (string) env('REVIEW_REQUEST_ELIGIBLE_STATUSES', '4,9,10'))
)));

return [
    // Slanje ostaje isključeno dok se migracija i dry-run ne provjere na produkciji.
    'request_emails_enabled' => env('REVIEW_REQUEST_EMAILS_ENABLED', false),
    'request_delay_days' => max(1, (int) env('REVIEW_REQUEST_DELAY_DAYS', 10)),
    // Initial catch-up and all later sends are restricted to recently created orders.
    'request_order_lookback_days' => max(1, (int) env('REVIEW_REQUEST_ORDER_LOOKBACK_DAYS', 60)),
    'request_daily_limit' => max(1, min((int) env('REVIEW_REQUEST_DAILY_LIMIT', 100), 1000)),
    'request_max_attempts' => max(1, (int) env('REVIEW_REQUEST_MAX_ATTEMPTS', 3)),
    'request_link_days' => max(1, (int) env('REVIEW_REQUEST_LINK_DAYS', 180)),
    'eligible_status_ids' => $eligibleStatuses ?: [4, 9, 10],
];
