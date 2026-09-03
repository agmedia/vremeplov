<?php

namespace App\Models\Front;

use App\Helpers\Currency;
use App\Helpers\Helper;
use App\Models\Back\Marketing\Action;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\ProductAction;
use App\Models\Front\Checkout\PaymentMethod;
use App\Models\Front\Checkout\ShippingMethod;
use App\Models\TagManager;
use App\Services\Inventory\OrderInventoryService;
use Darryldecode\Cart\CartCondition;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AgCart extends Model
{

    /**
     * @var string
     */
    private $cart_id;

    /**
     * @var
     */
    private $cart;

    /**
     * @var string
     */
    private $session_key;

    /**
     * @var string
     */
    private $coupon;


    /**
     * AgCart constructor.
     *
     * @param string $id
     */
    public function __construct(string $id)
    {
        $this->cart_id     = $id;
        $this->cart        = Cart::session($id);
        $this->session_key = config('session.cart') ?: 'agm';
        $this->coupon      = session()->has($this->session_key . '_coupon') ? session($this->session_key . '_coupon') : '';
    }


    /**
     * @return array
     */
    public function get()
    {
        $eur = $this->getEur();

        $response = [
            'id'              => $this->cart_id,
            'coupon'          => $this->coupon,
            'items'           => $this->cart->getContent(),
            'count'           => $this->cart->getTotalQuantity(),
            'subtotal'        => $this->cart->getSubTotal(),
            'conditions'      => $this->cart->getConditions(),
            'detail_con'      => $this->setCartConditions(),
            'total'           => $this->cart->getTotal(),
            'eur'             => $eur,
            'secondary_price' => $eur
        ];

        return $response;
    }


    /**
     * @param bool $just_basic
     *
     * @return Collection
     */
    public function getCartItems(bool $just_basic = false): Collection
    {
        $response = collect();

        foreach ($this->cart->getContent() as $item) {
            if ($just_basic) {
                $data = ['id' => $item->id, 'quantity' => $item->quantity];
                $response->push($data);
            } else {
                $response->push($item);
            }
        }

        return $response;
    }


    /**
     * @return null
     */

    public function getEur()
    {
        if (isset(Currency::secondary()->value)) {
            return Currency::secondary()->value;
        }

        return null;
    }




    /**
     * @param      $request
     * @param null $id
     *
     * @return array
     */
    public function check($request): array
    {
        // IDs sent by the browser can be stale or incomplete. The server-side cart
        // is the source of truth and every item in it must be checked.
        $items = $this->cart->getContent();
        $products = Product::query()
            ->whereIn('id', $items->pluck('id')->all())
            ->get(['id', 'name', 'quantity', 'status'])
            ->keyBy('id');
        $availableQuantities = $this->availableQuantitiesForCurrentCheckout($products);
        $messages = [];

        foreach ($items as $item) {
            $product = $products->get($item->id);
            $name = substr((string) ($product ? $product->name : $item->name), 0, 150);
            $available = $product ? max(0, (int) $availableQuantities->get($item->id, 0)) : 0;

            if (! $product || ! (bool) $product->status || $available === 0) {
                $this->cart->remove($item->id);
                $messages[] = 'Nažalost, knjiga ' . $name . ' više nije dostupna i uklonjena je iz košarice.';

                continue;
            }

            if ((int) $item->quantity > $available) {
                // An absolute update is important here; a relative update would add
                // the available amount to the already excessive cart quantity.
                $this->cart->update($item->id, [
                    'quantity' => [
                        'relative' => false,
                        'value' => $available,
                    ],
                ]);

                $messages[] = 'Dostupna količina za knjigu ' . $name . ' smanjena je na ' . $available . '.';
            }
        }

        return [
            'cart'    => $this->get(),
            'message' => $messages ? implode(' ', $messages) : null,
        ];
    }


    /**
     * Resolve stock usable by this cart in bulk. Checkout inventory reservations
     * can override this in one place without changing the cart validation rules.
     */
    protected function availableQuantitiesForCurrentCheckout(Collection $products): Collection
    {
        return app(OrderInventoryService::class)->availableForCurrentCheckout(
            $products->keys()->all()
        );
    }


    /**
     * @param      $request
     * @param null $id
     *
     * @return array
     */
    public function add($request, $id = null)
    {
        // Updejtaj artikl sa apsolutnom količinom.
        foreach ($this->cart->getContent() as $item) {
            if ($item->id == $request['item']['id']) {
                $quantity = $request['item']['quantity'];
                $product  = Product::where('id', $request['item']['id'])->first();

                if ($quantity > $product->quantity) {
                    return ['error' => 'Nažalost nema dovoljnih količina artikla..!'];
                }

                if ($quantity == 1 && ($item->quantity == 1 || $item->quantity > $quantity)) {
                    if ( ! $id) {
                        $quantity = $item->quantity + 1;
                    }
                }

                $relative = false;

                if (isset($request['item']['relative']) && $request['item']['relative']) {
                    $relative = true;
                }

                return $this->updateCartItem($item->id, $quantity, $relative);
            }
        }

        return $this->addToCart($request);
    }


    /**
     * @param $id
     *
     * @return array
     */
    public function remove($id)
    {
        $this->cart->remove($id);

        return $this->get();
    }


    /**
     * Provjeriti metodu da li se koristi negdje.
     *  ????????????????????????????????????????
     *
     * @param $coupon
     *
     * @return int
     */
    public function coupon($coupon): int
    {
        $items = $this->cart->getContent();

        // Refreshaj košaricu sa upisanim kuponom.
        foreach ($items as $item) {
            $this->remove($item->id);
            $this->addToCart($this->resolveItemRequest($item));
        }

        $has_coupon = ProductAction::active()->where('coupon', $coupon)->get();

        if ($has_coupon->count()) {
            return 1;
        }

        return 0;
    }


    /**
     *
     * @return array
     */
    public function flush()
    {
        if ($this->coupon != '') {
            $is_used = Helper::isCouponUsed($this->cart);

            if ($is_used != '') {
                $action = Action::query()->where('coupon', $is_used)->first();

                if ($action && $action->quantity == 1) {
                    $action->update(['status' => 0]);
                }
            }
        }

        $this->cart->clear();

        Helper::flushCache('cart', $this->cart_id);

        return $this;
    }


    /**
     * @param $item
     *
     * @return array[]
     */
    public function resolveItemRequest($item)
    {
        return [
            'item' => [
                'id'       => $item['id'],
                'quantity' => $item['quantity']
            ]
        ];
    }


    /**
     * If user is logged store or update the DB session.
     *
     * @return $this
     */
    public function resolveDB(array $data = null): static
    {
        if ( ! $data) {
            $data = $this->get();
        }

        if (Auth::user()) {
            $has_cart = \App\Models\Cart::where('user_id', Auth::user()->id)->first();

            if ($has_cart) {
                \App\Models\Cart::edit($data);
            } else {
                \App\Models\Cart::store($data);
            }
        }

        return $this;
    }


    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    public function setCartConditions()
    {
        $this->cart->clearCartConditions();

        $shipping_method   = ShippingMethod::condition($this->cart);
        $payment_method    = PaymentMethod::condition($this->cart);
        $special_condition = Helper::hasSpecialCartCondition($this->cart);
        $coupon_conditions = Helper::hasCouponCartConditions($this->cart, $this->coupon);

        if ($payment_method) {
            $str = str_replace('+', '', $payment_method->getValue());
            if (number_format(floatval($str), 2) > 0) {
                $this->cart->condition($payment_method);
            }
        }

        if ($shipping_method) {
            $this->cart->condition($shipping_method);
        }

        if ($special_condition) {
            $this->cart->condition($special_condition);
        }

        if ($coupon_conditions) {
            $this->cart->condition($coupon_conditions);
        }

        // Style response array
        $response = [];

        foreach ($this->cart->getConditions() as $condition) {
            $value = $condition->getValue();

            $response[] = [
                'name'       => $condition->getName(),
                'type'       => $condition->getType(),
                'target'     => 'total', // this condition will be applied to cart's subtotal when getSubTotal() is called.
                'value'      => $value,
                'attributes' => $condition->getAttributes()
            ];
        }

        return $response;
    }


    /**
     * @param $request
     *
     * @return array
     */
    private function addToCart($request): array
    {
        $item = $this->structureCartItem($request);

        // Ako je structureCartItem vratio grešku, odmah je proslijedi van
        if (isset($item['error'])) {
            return ['error' => $item['error']];
        }

        $this->cart->add($item);

        return $this->get();
    }


    /**
     * @param      $id
     * @param      $quantity
     * @param bool $relative
     *
     * @return array
     */
    private function updateCartItem($id, $quantity, bool $relative): array
    {
        $this->cart->update($id, [
            'quantity' => [
                'relative' => $relative,
                'value'    => $quantity
            ],
        ]);

        return $this->get();
    }


    /**
     * @param $request
     *
     * @return array
     */
    private function structureCartItem($request)
    {
        if (
            !isset($request['item']['id']) ||
            !isset($request['item']['quantity']) ||
            !is_numeric($request['item']['quantity']) ||
            $request['item']['quantity'] < 1
        ) {
            return ['error' => 'Neispravan zahtjev za artikl.'];
        }

        $product = Product::where('id', $request['item']['id'])->first();
        if (!$product) {
            return ['error' => 'Artikl nije pronađen.'];
        }

        $product->dataLayer = TagManager::getGoogleProductDataLayer($product);

        if ($request['item']['quantity'] > $product->quantity) {
            return ['error' => 'Nažalost nema dovoljnih količina artikla..!'];
        }

        $response = [
            'id'              => $product->id,
            'name'            => $product->name,
            'price'           => $product->price,
            'sec_price'       => $product->secondary_price,
            'quantity'        => (int) $request['item']['quantity'],
            'associatedModel' => $product,
            'attributes'      => $this->structureCartItemAttributes($product),
        ];

        if ($conditions = $this->structureCartItemConditions($product)) {
            $response['conditions'] = $conditions;
        }

        return $response;
    }


    /**
     * @param $product
     *
     * @return string[]
     */
    private function structureCartItemAttributes($product)
    {
        return [
            'path' => $product->url,
            'tax'  => $product->tax($product->tax_id)
        ];
    }


    /**
     * @param $product
     *
     * @return CartCondition|bool
     * @throws \Darryldecode\Cart\Exceptions\InvalidConditionException
     */
    private function structureCartItemConditions($product)
    {
        // Ako artikl ima akciju.
        if ($product->special()) {
            $coupon = $product->coupon();

            if ($coupon != '') {
                return new CartCondition([
                    'name'   => 'Kupon akcija',
                    'type'   => 'coupon',
                    'target' => $coupon,
                    'value'  => -($product->price - $product->special())
                ]);
            }

            return new CartCondition([
                'name'   => 'Akcija',
                'type'   => 'promo',
                'target' => '',
                'value'  => -($product->price - $product->special())
            ]);
        }

        // Ako nema akcije na artiklu.
        // Ako nije ispravan kupon.
        return false;
    }

}
