<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    'recaptcha' => [
        'sitekey'    => env('GOOGLE_RECAPTCHA_SITE_KEY'),
        'secret'     => env('GOOGLE_RECAPTCHA_SECRET_KEY'),
        'verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
    ],

    'mailchimp' => [
        'api_key'       => env('MAILCHIMP_API_KEY'),
        'server_prefix' => env('MAILCHIMP_SERVER_PREFIX'),
    ],

    // Vrijednosti su samo sigurni fallback. Produkcijske postavke uređuju se
    // u adminu pod Postavke > Načini dostave > Box Now.
    'boxnow' => [
        'base_url' => 'https://api-production.boxnow.hr/api/v1',
        'client_id' => '',
        'client_secret' => '',
        'api_partner_id' => '',
        'widget_partner_id' => 123,
        'order_prefix' => 'VREMEPLOV',
        'warehouse_location_id' => '',
        'origin_name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Vremeplov')),
        'origin_email' => env('MAIL_FROM_ADDRESS', ''),
        'origin_phone' => '',
        'tracking_url' => 'https://track.boxnow.hr/en?track={parcel}',
        'allow_return' => true,
        'cod_enabled' => false,
        'email_label_on_create' => true,
    ],

    /*******************************************************************************
     *                              END Copyright : AGmedia                         *
     *******************************************************************************/

];
