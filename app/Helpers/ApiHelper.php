<?php

namespace App\Helpers;


class ApiHelper
{

    /**
     * @param int    $status
     * @param string $text
     *
     * @return string
     */
    public static function response(int $status, string $text): string
    {
        return static::resultBadge($status) . '<br><br><span class="h5 font-w300">' . $text . '</span>';
    }


    /**
     * @param int $status
     *
     * @return string
     */
    public static function resultBadge(int $status): string
    {
        if ($status) {
            return '<span class="badge badge-success font-size-h5">Uspjeh</span>';
        }

        return '<span class="badge badge-danger font-size-h5">Greška</span>';
    }
}
