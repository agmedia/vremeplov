<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Name of route
    |--------------------------------------------------------------------------
    |
    | Enter the routes name to enable dynamic imagecache manipulation.
    | This handle will define the first part of the URI:
    |
    | {route}/{template}/{filename}
    |
    | Examples: "images", "img/cache"
    |
    */

    'route' => null,

    /*
    |--------------------------------------------------------------------------
    | Storage paths
    |--------------------------------------------------------------------------
    |
    | The following paths will be searched for the image filename, submitted
    | by URI.
    |
    | Define as many directories as you like.
    |
    */

    'paths' => [
        public_path('upload'),
        public_path('images')
    ],

    /*
    |--------------------------------------------------------------------------
    | Manipulation templates
    |--------------------------------------------------------------------------
    |
    | Here you may specify your own manipulation filter templates.
    | The keys of this array will define which templates
    | are available in the URI:
    |
    | {route}/{template}/{filename}
    |
    | The values of this array will define which filter class
    | will be applied, by its fully qualified name.
    |
    */

    'templates' => [
        'small' => 'Intervention\Image\Templates\Small',
        'medium' => 'Intervention\Image\Templates\Medium',
        'large' => 'Intervention\Image\Templates\Large',
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Cache Lifetime
    |--------------------------------------------------------------------------
    |
    | Lifetime in minutes of the images handled by the imagecache route.
    |
    */

    'lifetime' => 60,//43200,

    /*
    |--------------------------------------------------------------------------
    | Dynamic image source limits
    |--------------------------------------------------------------------------
    |
    | The legacy cache endpoints may only read raster images already available
    | through this application. Remote URLs are never fetched. These limits are
    | checked before Intervention Image decodes the file.
    |
    */
    'source_roots' => [
        public_path(),
        storage_path('app/public'),
    ],
    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ],
    'max_source_length' => 2048,
    'max_source_bytes' => 8 * 1024 * 1024,
    'max_source_dimension' => 8000,
    'max_source_pixels' => 12000000,
    'max_thumb_dimension' => 1200,
    'max_thumb_pixels' => 1440000,
    // Production access logs currently use 100x100; keep bounded standard
    // variants for existing/default and future high-density storefront images.
    'allowed_thumb_sizes' => [
        '100x100',
        '400x400',
        '800x800',
    ],

];
