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
        'bypass_local' => env('GOOGLE_RECAPTCHA_BYPASS_LOCAL', true),
    ],

    // KEKS Pay callbacks and status polling remain unavailable until the
    // integration is explicitly reviewed and enabled for this installation.
    'keks' => [
        'enabled' => env('KEKS_PAY_ENABLED', false),
    ],

    'mailchimp' => [
        'api_key'       => env('MAILCHIMP_API_KEY'),
        'server_prefix' => env('MAILCHIMP_SERVER_PREFIX'),
    ],

    'gls' => [
        'client_number' => env('GLS_CLIENT_NUMBER'),
        'username' => env('GLS_USERNAME'),
        'password' => env('GLS_PASSWORD'),
        'wsdl' => env('GLS_WSDL', 'https://api.mygls.hr/ParcelService.svc?singleWsdl'),
        'connection_timeout' => (int) env('GLS_CONNECTION_TIMEOUT', 20),
        'tracking_url' => env('GLS_TRACKING_URL', 'https://gls-group.com/GROUP/en/parcel-tracking?match={tracking_code}'),
        'pickup' => [
            'contact_name' => env('GLS_PICKUP_CONTACT_NAME'),
            'contact_phone' => env('GLS_PICKUP_CONTACT_PHONE'),
            'contact_email' => env('GLS_PICKUP_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS')),
            'name' => env('GLS_PICKUP_NAME', env('APP_NAME', 'Antikvarijat Vremeplov')),
            'street' => env('GLS_PICKUP_STREET'),
            'house_number' => env('GLS_PICKUP_HOUSE_NUMBER'),
            'city' => env('GLS_PICKUP_CITY'),
            'zip_code' => env('GLS_PICKUP_ZIP_CODE'),
            'country_code' => env('GLS_PICKUP_COUNTRY_CODE', 'HR'),
        ],
    ],

    'bank_transfer' => [
        'barcode_url' => env('BANK_BARCODE_URL', 'https://hub3.bigfish.software/api/v2/barcode'),
        'receiver_name' => env('BANK_RECEIVER_NAME', 'Vremeplov razglednica d.o.o.'),
        'receiver_street' => env('BANK_RECEIVER_STREET', 'Zvonimirova 24'),
        'receiver_place' => env('BANK_RECEIVER_PLACE', '10000 Zagreb'),
        'iban' => env('BANK_IBAN', 'HR4524020061100571694'),
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
