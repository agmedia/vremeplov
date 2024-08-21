<?php

namespace App\Helpers\Session;

class CheckoutSession
{

    /**
     * @var string
     */
    private static $session_string = 'checkout';

    /*******************************************************************************
    *                                Copyright : AGmedia                           *
    *                              email: filip@agmedia.hr                         *
    *******************************************************************************/

    /**
     * SHIPPING ADDRESS DATA
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public static function getAddress()
    {
        return session(static::$session_string . '.address');
    }


    /**
     * @return bool
     */
    public static function hasAddress()
    {
        return session()->has(static::$session_string . '.address');
    }


    /**
     * @param array $value
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public static function setAddress(array $value)
    {
        return session([static::$session_string . '.address' => $value]);
    }


    /**
     * @return bool
     */
    public static function forgetAddress()
    {
        return session()->forget(static::$session_string . '.address');
    }

    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * SHIPPING
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public static function getShipping()
    {
        return session(static::$session_string . '.shipping');
    }


    /**
     * @return bool
     */
    public static function hasShipping()
    {
        return session()->has(static::$session_string . '.shipping');
    }


    /**
     * @param array|string $value
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public static function setShipping($value)
    {
        return session([static::$session_string . '.shipping' => $value]);
    }


    /**
     * @return bool
     */
    public static function forgetShipping()
    {
        return session()->forget(static::$session_string . '.shipping');
    }

    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * SHIPPING
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public static function getComment()
    {
        return session(static::$session_string . '.comment');
    }


    /**
     * @return bool
     */
    public static function hasComment()
    {
        return session()->has(static::$session_string . '.comment');
    }


    /**
     * @param array|string $value
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public static function setComment($value)
    {
        return session([static::$session_string . '.comment' => $value]);
    }


    /**
     * @return bool
     */
    public static function forgetComment()
    {
        return session()->forget(static::$session_string . '.comment');
    }



    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * SHIPPING
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public static function getCommentp()
    {
        return session(static::$session_string . '.commentp');
    }


    /**
     * @return bool
     */
    public static function hasCommentp()
    {
        return session()->has(static::$session_string . '.commentp');
    }


    /**
     * @param array|string $value
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public static function setCommentp($value)
    {
        return session([static::$session_string . '.commentp' => $value]);
    }


    /**
     * @return bool
     */
    public static function forgetCommentp()
    {
        return session()->forget(static::$session_string . '.commentp');
    }

    /*******************************************************************************
    *                                Copyright : AGmedia                           *
    *                              email: filip@agmedia.hr                         *
    *******************************************************************************/

    /**
     * PAYMENT
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public static function getPayment()
    {
        return session(static::$session_string . '.payment');
    }


    /**
     * @return bool
     */
    public static function hasPayment()
    {
        return session()->has(static::$session_string . '.payment');
    }


    /**
     * @param array|string $value
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public static function setPayment($value)
    {
        return session([static::$session_string . '.payment' => $value]);
    }


    /**
     * @return bool
     */
    public static function forgetPayment()
    {
        return session()->forget(static::$session_string . '.payment');
    }

    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * STEPS
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public static function getStep()
    {
        return session(static::$session_string . '.step');
    }


    /**
     * @return bool
     */
    public static function hasStep()
    {
        return session()->has(static::$session_string . '.step');
    }


    /**
     * @param array|string $value
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public static function setStep($value)
    {
        return session([static::$session_string . '.step' => $value]);
    }


    /**
     * @return bool
     */
    public static function forgetStep()
    {
        return session()->forget(static::$session_string . '.step');
    }

    /*******************************************************************************
    *                                Copyright : AGmedia                           *
    *                              email: filip@agmedia.hr                         *
    *******************************************************************************/

    /**
     * GEO ZONE
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public static function getGeoZone()
    {
        return session(static::$session_string . '.geo_zone');
    }


    /**
     * @return bool
     */
    public static function hasGeoZone()
    {
        return session()->has(static::$session_string . '.geo_zone');
    }


    /**
     * @param array|string $value
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public static function setGeoZone($value)
    {
        return session([static::$session_string . '.geo_zone' => $value]);
    }


    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * STEPS
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public static function getOrder()
    {
        return session(static::$session_string . '.order');
    }


    /**
     * @return bool
     */
    public static function hasOrder()
    {
        return session()->has(static::$session_string . '.order');
    }


    /**
     * @param array|string $value
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public static function setOrder($value)
    {
        return session([static::$session_string . '.order' => $value]);
    }


    /**
     * @return bool
     */
    public static function forgetOrder()
    {
        return session()->forget(static::$session_string . '.order');
    }

    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * @return void
     */
    public static function forgetCheckout(): void
    {
        CheckoutSession::forgetOrder();
        CheckoutSession::forgetStep();
        CheckoutSession::forgetPayment();
        CheckoutSession::forgetShipping();
        CheckoutSession::forgetComment();
    }
}
