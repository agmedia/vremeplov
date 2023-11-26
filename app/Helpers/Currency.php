<?php

namespace App\Helpers;

use App\Models\Back\Settings\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Currency
{

    /**
     * @return Collection
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public static function list(): Collection
    {
        return Cache::rememberForever('currency_list', function () {
            return Settings::get('currency', 'list');
        });
    }


    /**
     * @param      $price
     * @param bool $text_price
     *
     * @return Collection|string|bool
     */
    public static function main($price = null, bool $text_price = false)
    {
        $currency = Cache::rememberForever('currency_main', function () {
            return Settings::get('currency', 'list')->where('status', '=', true)->where('main', '=', true)->first();
        });

        return static::resolveCurrency($currency, $price, $text_price);
    }


    /**
     * @param      $price
     * @param bool $text_price
     *
     * @return Collection|string|bool
     */
    public static function secondary($price = null, bool $text_price = false)
    {
        $currency = Cache::rememberForever('currency_secondary', function () {
            return Settings::get('currency', 'list')->where('status', '=', true)->where('main', '=', false)->first();
        });

        return static::resolveCurrency($currency, $price, $text_price);
    }


    /**
     * @param      $currency
     * @param      $price
     * @param bool $text_price
     *
     * @return false|mixed|string
     */
    private static function resolveCurrency($currency, $price, bool $text_price = false)
    {
        if ($currency) {
            if ($price) {
                return static::resolvePrice($currency, $price, $text_price);
            }

            return $currency;
        }

        return false;
    }


    /**
     * @param stdClass   $currency
     * @param            $price
     * @param bool       $text_price
     *
     * @return string
     */
    private static function resolvePrice(\stdClass $currency, $price, bool $text_price = false): string
    {
        if ($text_price) {
            $left  = $currency->symbol_left ? $currency->symbol_left . '' : '';
            $right = $currency->symbol_right ? '' . $currency->symbol_right : '';

            return $left . number_format(($price * $currency->value), $currency->decimal_places, ',', '.') . $right;
        }

        return number_format(($price * $currency->value), $currency->decimal_places, '.', '');
    }
}
