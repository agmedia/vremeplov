<?php

namespace App\Models\Front\Checkout;

use App\Helpers\Session\CheckoutSession;
use App\Models\Back\Settings\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class ShippingMethod
 * @package App\Models\Front\Checkout
 */
class ShippingMethod
{

    /**
     * @var array|false|Collection
     */
    protected $methods;

    /**
     * @var mixed|null
     */
    protected $response_methods = null;


    /**
     * ShippingMethod constructor.
     */
    public function __construct()
    {
        $this->methods = $this->list();
        $this->response_methods = collect();
    }


    /**
     * @param bool $only_active
     *
     * @return array|false|Collection
     */
    public function list(bool $only_active = true)
    {
        return Settings::getList('shipping', 'list.%', $only_active);
    }


    /**
     * @param int $id
     *
     * @return mixed
     */
    public function id(int $id)
    {
        return $this->methods->where('id', $id)->first();
    }


    /**
     * @param string $code
     *
     * @return mixed
     */
    public function find(string $code)
    {
        //Log::info($this->methods->where('code', $code)->first()->code);
        return $this->methods->where('code', $code)->first();
    }


    /**
     * @param int $zone
     *
     * @return $this
     */
    public function findGeo(int $zone)
    {
        foreach ($this->methods as $method) {
            if ($method->geo_zone == $zone) {
                $this->response_methods->push($method);
            }
        }

        return $this;
    }


    /**
     * @param array $cart
     *
     * @return $this
     */
    public function checkCart(array $cart)
    {
        /*$pass = true;

        if (isset($cart['items'])) {
            foreach ($cart['items'] as $item) {
                if ($item['associatedModel']['origin'] != 'Hrvatski') {
                    $pass = false;
                }
            }
        }

        if ( ! $pass) {
            $methods = $this->response_methods;
            $this->response_methods = collect();

            foreach ($methods as $method) {
                if ($method->code != 'flat') {
                    $this->response_methods->push($method);
                }
            }
        }*/

        return $this;
    }


    /**
     * @return Collection
     */
    public function resolve(): Collection
    {
        return $this->response_methods;
    }


    /**
     * @param $cart
     *
     * @return \Darryldecode\Cart\CartCondition|false
     * @throws \Darryldecode\Cart\Exceptions\InvalidConditionException
     */
    public static function condition($cart = null)
    {
        $shipping = false;
        $condition = false;

        if (CheckoutSession::hasShipping()) {
            $shipping = (new ShippingMethod())->find(CheckoutSession::getShipping());
        }

        if ($shipping) {
            $value = $shipping->data->price;

            if ($cart->getTotal() > config('settings.free_shipping')) {
                $value = 0;
            }

            $condition = new \Darryldecode\Cart\CartCondition(array(
                'name' => $shipping->title,
                'type' => 'shipping',
                'target' => 'total', // this condition will be applied to cart's subtotal when getSubTotal() is called.
                'value' => '+' . $value,
                'attributes' => [
                    'description' => $shipping->data->short_description,
                    'geo_zone' => $shipping->geo_zone
                ]
            ));
        }

        return $condition;
    }
}