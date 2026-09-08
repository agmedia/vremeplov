<?php

namespace App\Helpers;


class Metatags
{

    public static function noFollow()
    {
        return [
            'name' => 'robots',
            'content' => 'noindex,follow'
        ];
    }
}
