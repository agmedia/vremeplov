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
use Darryldecode\Cart\CartCondition;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AgCart extends Model
{
    /**
     * @var string
     */
    private $cart_id;

    /**
     * @var \Darryldecode\Cart\Cart
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
     * Vraća stabilizirani payload košarice.
     *
     * @return array
     */
    public function get(): array
    {
        $eur = $this->getEur();

        // Pazimo da su numeric polja uvijek numerička i da polja postoje.
        return [
            'id'              => $this->cart_id,
            'coupon'          => $this->coupon ?: '',
            'items'           => $this->cart->getContent(),                    // Collection -> u JSON-u niz stavki
            'count'           => (int) $this->cart->getTotalQuantity(),
            'subtotal'        => (float) $this->cart->getSubTotal(),
            'conditions'      => $this->cart->getConditions(),
            'detail_con'      => $this->setCartConditions(),                   // stilizirani uvjeti
            'total'           => (float) $this->cart->getTotal(),
            'eur'             => $eur,                                         // može biti null
            'secondary_price' => (bool) $eur,
        ];
    }

    /**
     * @param bool $just_basic
     * @return Collection
     */
    public function getCartItems(bool $just_basic = false): Collection
    {
        $response = collect();

        foreach ($this->cart->getContent() as $item) {
            if ($just_basic) {
                $response->push(['id' => $item->id, 'quantity' => $item->quantity]);
            } else {
                $response->push($item);
            }
        }

        return $response;
    }

    /**
     * @return mixed|null
     */
    public function getEur()
    {
        if (isset(Currency::secondary()->value)) {
            return Currency::secondary()->value;
        }

        return null;
    }

    /**
     * Provjera dostupnosti; uvijek vraća { cart, message }.
     *
     * @param array $request
     * @return array
     */
    public function check($request): array
    {
        $ids = isset($request['ids']) && is_array($request['ids']) ? $request['ids'] : [];

        $message = null;

        if (!empty($ids)) {
            $products = Product::whereIn('id', $ids)->pluck('quantity', 'id');

            foreach ($products as $id => $quantity) {
                if (!$quantity) {
                    $this->remove((int) $id);

                    if ($product = Product::find((int) $id)) {
                        $message = 'Nažalost, knjiga ' . substr($product->name, 0, 150) . ' više nije dostupna.';
                    } else {
                        $message = 'Nažalost, artikl više nije dostupan.';
                    }
                }
            }
        }

        return [
            'cart'    => $this->get(),
            'message' => $message,
        ];
    }

    /**
     * Dodaj ili ažuriraj postojeću stavku.
     *
     * @param array    $request
     * @param int|null $id
     * @return array
     */
    public function add($request, $id = null): array
    {
        // Updejtaj artikl s apsolutnom količinom ako već postoji u košarici.
        foreach ($this->cart->getContent() as $item) {
            if ($item->id == $request['item']['id']) {
                $quantity = (int) $request['item']['quantity'];
                $product  = Product::find($request['item']['id']);

                if (!$product) {
                    return ['error' => 'Artikl ne postoji.'];
                }

                if ($quantity > $product->quantity) {
                    return ['error' => 'Nažalost nema dovoljnih količina artikla..!'];
                }

                if ($quantity == 1 && ($item->quantity == 1 || $item->quantity > $quantity)) {
                    if (!$id) {
                        $quantity = $item->quantity + 1;
                    }
                }

                $relative = isset($request['item']['relative']) && $request['item']['relative'] ? true : false;

                return $this->updateCartItem($item->id, $quantity, $relative);
            }
        }

        // Inače – dodaj novu stavku.
        return $this->addToCart($request);
    }

    /**
     * @param int $id
     * @return array
     */
    public function remove($id): array
    {
        $this->cart->remove($id);

        return $this->get();
    }

    /**
     * Provjeriti metodu da li se koristi negdje (ostavljena identična signatura/ponašanje).
     *
     * @param string $coupon
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

        return $has_coupon->count() ? 1 : 0;
    }

    /**
     * Očisti košaricu i VRATI VALJANI PAYLOAD (ne model).
     *
     * @return array
     */
    public function flush(): array
    {
        if ($this->coupon !== '') {
            $is_used = Helper::isCouponUsed($this->cart);

            if ($is_used !== '') {
                $action = Action::query()->where('coupon', $is_used)->first();

                if ($action && (int) $action->quantity === 1) {
                    $action->update(['status' => 0]);
                }
            }
        }

        $this->cart->clear();
        Helper::flushCache('cart', $this->cart_id);

        // Vrati uvijek stabilizirani, “prazni” prikaz košarice.
        return $this->get();
    }

    /**
     * @param $item
     * @return array[]
     */
    public function resolveItemRequest($item): array
    {
        return [
            'item' => [
                'id'       => $item['id'],
                'quantity' => $item['quantity'],
            ],
        ];
    }

    /**
     * Ako je korisnik logiran, spremi/azuriraj DB snapshot košarice.
     *
     * @param array|null $data
     * @return static
     */
    public function resolveDB(array $data = null): static
    {
        if (!$data) {
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
     *                                Copyright : AG media                           *
     *                              email: filip@agmedia.hr                          *
     *******************************************************************************/

    /**
     * Resetira i preračunava uvjete košarice te vraća stilizirani niz za frontend.
     *
     * @return array
     */
    public function setCartConditions(): array
    {
        $this->cart->clearCartConditions();

        $shipping_method   = ShippingMethod::condition($this->cart);
        $payment_method    = PaymentMethod::condition($this->cart);
        $special_condition = Helper::hasSpecialCartCondition($this->cart);
        $coupon_conditions = Helper::hasCouponCartConditions($this->cart, $this->coupon);

        if ($payment_method) {
            $str = str_replace('+', '', $payment_method->getValue());
            if (number_format((float) $str, 2) > 0) {
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
                'target'     => 'total',
                'value'      => $value,
                'attributes' => $condition->getAttributes(),
            ];
        }

        return $response;
    }

    /**
     * @param array $request
     * @return array
     */
    private function addToCart($request): array
    {
        $this->cart->add($this->structureCartItem($request));

        return $this->get();
    }

    /**
     * @param int  $id
     * @param int  $quantity
     * @param bool $relative
     * @return array
     */
    private function updateCartItem($id, $quantity, bool $relative): array
    {
        $this->cart->update($id, [
            'quantity' => [
                'relative' => $relative,
                'value'    => (int) $quantity,
            ],
        ]);

        return $this->get();
    }

    /**
     * @param array $request
     * @return array
     */
    private function structureCartItem($request): array
    {
        $productId = $request['item']['id'] ?? null;
        $qty       = (int) ($request['item']['quantity'] ?? 0);

        if (!$productId || $qty < 1) {
            return ['error' => 'Neispravan zahtjev.'];
        }

        $product = Product::find($productId);

        if (!$product) {
            return ['error' => 'Artikl ne postoji.'];
        }

        $product->dataLayer = TagManager::getGoogleProductDataLayer($product);

        if ($qty > $product->quantity) {
            return ['error' => 'Nažalost nema dovoljnih količina artikla..!'];
        }

        $response = [
            'id'              => $product->id,
            'name'            => $product->name,
            'price'           => $product->price,
            'sec_price'       => $product->secondary_price,
            'quantity'        => $qty,
            'associatedModel' => $product,
            'attributes'      => $this->structureCartItemAttributes($product),
        ];

        $conditions = $this->structureCartItemConditions($product);

        if ($conditions) {
            $response['conditions'] = $conditions;
        }

        return $response;
    }

    /**
     * @param \App\Models\Front\Catalog\Product $product
     * @return array
     */
    private function structureCartItemAttributes($product): array
    {
        return [
            'path' => $product->url,
            'tax'  => $product->tax($product->tax_id),
        ];
    }

    /**
     * @param \App\Models\Front\Catalog\Product $product
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
                    'value'  => -($product->price - $product->special()),
                ]);
            }

            return new CartCondition([
                'name'   => 'Akcija',
                'type'   => 'promo',
                'target' => '',
                'value'  => -($product->price - $product->special()),
            ]);
        }

        // Ako nema akcije na artiklu / nije ispravan kupon.
        return false;
    }
}
