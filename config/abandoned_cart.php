<?php

return [
    // Enable only after the legal basis and the production sender have been approved.
    'enabled' => env('ABANDONED_CART_EMAILS_ENABLED', false),
    'starts_at' => env('ABANDONED_CART_STARTS_AT', '2026-09-04 00:00:00'),
    'delays_minutes' => [
        1 => (int) env('ABANDONED_CART_FIRST_DELAY_MINUTES', 60),
        2 => (int) env('ABANDONED_CART_SECOND_DELAY_MINUTES', 1440),
    ],
    'max_reminders' => 2,
    'batch_size' => 25,
    'recovery_link_days' => 7,
];
