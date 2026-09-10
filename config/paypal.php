<?php
/**
 * PayPal Setting & API Credentials
 * Created by Raza Mehdi <srmk@outlook.com>.
 */

return [
    'mode'    => env('PAYPAL_MODE', 'sandbox'), // Can only be 'sandbox' Or 'live'. If empty or invalid, 'live' will be used.
    'sandbox' => [
        'client_id'         => env('PAYPAL_SANDBOX_CLIENT_ID', ''),
        'client_secret'     => env('PAYPAL_SANDBOX_CLIENT_SECRET', ''),
        'app_id'            => 'APP-80W284485P519543T',
    ],
    'live' => [
        'client_id'         => env('PAYPAL_LIVE_CLIENT_ID', ''),
        'client_secret'     => env('PAYPAL_LIVE_CLIENT_SECRET', ''),
        'app_id'            => env('PAYPAL_LIVE_APP_ID', ''),
    ],

    'reconciliation' => [
        'enabled' => env('PAYPAL_RECONCILIATION_ENABLED', true),
        // Missing rows are retried later; only positive exact matches mutate an order.
        'delay_minutes' => max(5, (int) env('PAYPAL_RECONCILIATION_DELAY_MINUTES', 15)),
        'lookback_days' => min(30, max(1, (int) env('PAYPAL_RECONCILIATION_LOOKBACK_DAYS', 30))),
        'batch_size' => max(1, (int) env('PAYPAL_RECONCILIATION_BATCH_SIZE', 200)),
        'page_size' => min(500, max(1, (int) env('PAYPAL_RECONCILIATION_PAGE_SIZE', 500))),
        'timeout_seconds' => max(3, (int) env('PAYPAL_RECONCILIATION_TIMEOUT_SECONDS', 10)),
    ],

    'payment_action' => env('PAYPAL_PAYMENT_ACTION', 'Sale'), // Can only be 'Sale', 'Authorization' or 'Order'
    'currency'       => env('PAYPAL_CURRENCY', 'USD'),
    'notify_url'     => env('PAYPAL_NOTIFY_URL', ''), // Change this accordingly for your application.
    'locale'         => env('PAYPAL_LOCALE', 'en_US'), // force gateway language  i.e. it_IT, es_ES, en_US ... (for express checkout only)
    'validate_ssl'   => env('PAYPAL_VALIDATE_SSL', true), // Validate SSL when creating api client.
];
