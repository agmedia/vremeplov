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

    'free_shipping' => 70,

    'pagination' => [
        'front' => 40,
        'back'  => 30
    ],

    'search_keyword'    => 'pojam',
    'author_path'       => 'autor',
    'publisher_path'    => 'nakladnik',
    'group_path'        => 'Kategorija proizvoda',
    'unknown_author'    => 1,
    'unknown_publisher' => 1,
    'images_domain'     => env('APP_IMAGE_DOMAIN'),
    'eur_divide_amount' => 0.13272280,
    //
    'default_product_image' => 'media/avatars/default_book.jpg',
    //
    // Specijalni popust, ako ga ima
    'special_action' => [
        'title' => 'Količinski popust',
        'start' => '04/11/2023 00:00:01',
        'end' => '12/11/2023 00:00:00'
    ],
    //
    'sorting_list' => [
        0 => [
            'title' => 'Najnovije',
            'value' => 'novi'
        ],
        1 => [
            'title' => 'Najmanja cijena',
            'value' => 'price_up'
        ],
        2 => [
            'title' => 'Najveća cijena',
            'value' => 'price_down'
        ],
        3 => [
            'title' => 'A - Ž',
            'value' => 'naziv_up'
        ],
        4 => [
            'title' => 'Ž - A',
            'value' => 'naziv_down'
        ],
    ],

    'order' => [
        'made_text' => 'Narudžba napravljena.',
        'status'    => [
            'new'        => 1,
            'unfinished' => 8,
            'declined'   => 7,
            'canceled'   => 5,
            'paid'       => 3,
            'send'       => 4,
            'ready'      => 10,
        ],
        // Can be number or array.
        'new_status' => 1,
        'canceled_status' => [7, 5],
    ],

    'payment' => [
        'providers' => [
            'wspay'  => \App\Models\Front\Checkout\Payment\Wspay::class,
            'paypal'  => \App\Models\Front\Checkout\Payment\PayPalStandard::class,
            //'payway' => \App\Models\Front\Checkout\Payment\Payway::class,
            //'corvus' => \App\Models\Front\Checkout\Payment\Corvus::class,
            'cod'    => \App\Models\Front\Checkout\Payment\Cod::class,
            'bank'   => \App\Models\Front\Checkout\Payment\Bank::class,
            'pickup' => \App\Models\Front\Checkout\Payment\Pickup::class
        ]
    ],

    'sitemap' => [
        0 => 'pages',
        1 => 'categories',
        2 => 'products',
        3 => 'authors',
        4 => 'publishers'
    ],

    'njuskalo' => [
        'user_id' => '2168914',
        'sync' => [
            'ambalaza' => 9760, //*
            'antikviteti' => 9750,
            'dionice' => 9760, //*
            'diplome' => 9760, //*
            'heraldika' => 9760, //*
            'jelovnici' => 9760, //*
            'novine-i-casopisi' => 15347,
            'reklame' => 9760, //*
            'stari-dokumenti' => 9800,
            'knjige' => 9760, // Ostala literatura
            'razglednice' => 12799,
            'plakati' => 13107,
            'zamljopisne-karte' => 9760 //*
        ]
    ]

];
