<?php

namespace App\Http\Controllers\Front;

use App\Helpers\Session\CheckoutSession;
use App\Http\Controllers\Controller;
use App\Models\Back\Orders\Order;
use App\Models\Front\AgCart;
use App\Models\Front\Catalog\Product;
use App\Services\Orders\AbandonedCartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class AbandonedCartRecoveryController extends Controller
{
    public function __invoke(Order $order, AbandonedCartService $service): RedirectResponse
    {
        if (! $service->canRecover($order)) {
            return redirect()->route('index')->with('error', 'Ova poveznica za nastavak kupnje više nije dostupna.');
        }

        $cartKey = (string) config('session.cart');
        $cartId = session($cartKey);
        if (! is_string($cartId) || $cartId === '') {
            $cartId = Str::random(24);
            session([$cartKey => $cartId]);
        }

        $cart = new AgCart($cartId);
        $existingIds = $cart->getCartItems(true)->pluck('id')->map(function ($id) {
            return (int) $id;
        });
        $restored = 0;

        foreach ($order->products as $item) {
            if ($existingIds->contains((int) $item->product_id)) {
                $restored++;
                continue;
            }

            $product = Product::query()->whereKey($item->product_id)
                ->where('status', 1)->where('quantity', '>', 0)->first();
            if (! $product) {
                continue;
            }

            $quantity = min((int) $item->quantity, (int) $product->quantity);
            if ($quantity > 0 && ! isset($cart->add(['item' => [
                'id' => (int) $product->id,
                'quantity' => $quantity,
            ]])['error'])) {
                $restored++;
                $existingIds->push((int) $product->id);
            }
        }

        if ($restored === 0) {
            return redirect()->route('index')->with('error', 'Odabrane knjige trenutačno više nisu dostupne.');
        }

        CheckoutSession::setOrder(['id' => (int) $order->id]);
        CheckoutSession::setAddress([
            'fname' => (string) $order->payment_fname,
            'lname' => (string) $order->payment_lname,
            'email' => (string) $order->payment_email,
            'phone' => (string) $order->payment_phone,
            'address' => (string) $order->payment_address,
            'city' => (string) $order->payment_city,
            'zip' => (string) $order->payment_zip,
            'company' => (string) ($order->company ?? ''),
            'oib' => (string) ($order->oib ?? ''),
            'state' => (string) ($order->payment_state ?: 'Croatia'),
        ]);
        CheckoutSession::setShipping((string) $order->shipping_code);
        CheckoutSession::setPayment((string) $order->payment_code);
        CheckoutSession::setComment((string) ($order->comment ?? ''));
        CheckoutSession::setCommentp((string) ($order->commentp ?? ''));
        CheckoutSession::setStep('podaci');

        return redirect()->route('kosarica')->with(
            'success',
            'Košarica je obnovljena. Prije potvrde provjerit ćemo cijene, dostupnost, dostavu i plaćanje.'
        );
    }
}
