<?php

namespace Tests\Unit;

use App\Helpers\Session\CheckoutSession;
use App\Http\Livewire\Front\Checkout;
use Illuminate\Support\Collection;
use Tests\TestCase;

class BoxNowCheckoutStateTest extends TestCase
{
    public function test_switching_to_boxnow_clears_old_locker_and_opens_boxnow_picker(): void
    {
        CheckoutSession::setShipping('gls_paketomat');
        CheckoutSession::setCommentp('Stari GLS paketomat_OLD-1');

        CheckoutSession::setPayment('corvus');

        $checkout = new CheckoutWithAvailableMethods();
        $checkout->commentp = CheckoutSession::getCommentp();
        $checkout->payment = CheckoutSession::getPayment();
        $checkout->selectShipping('boxnow');

        $this->assertSame('boxnow', CheckoutSession::getShipping());
        $this->assertNull(CheckoutSession::getCommentp());
        $this->assertSame('', $checkout->commentp);
        $this->assertNull(CheckoutSession::getPayment());
        $this->assertSame('', $checkout->payment);
        $this->assertTrue($checkout->view_boxnow);
        $this->assertFalse($checkout->view_commentp);
    }

    public function test_tampered_shipping_code_is_not_saved_to_checkout_session(): void
    {
        CheckoutSession::setShipping('boxnow');

        $checkout = new CheckoutWithAvailableMethods();
        $checkout->shipping = 'boxnow';
        $checkout->selectShipping('not-an-available-method');

        $this->assertSame('boxnow', CheckoutSession::getShipping());
        $this->assertSame('boxnow', $checkout->shipping);
    }

    public function test_tampered_payment_code_is_rejected_and_cleared(): void
    {
        CheckoutSession::setPayment('corvus');

        $checkout = new CheckoutWithAvailableMethods();
        $checkout->shipping = 'boxnow';
        $checkout->payment = 'corvus';
        $checkout->selectPayment('cod');

        $this->assertNull(CheckoutSession::getPayment());
        $this->assertSame('', $checkout->payment);
    }

    public function test_boxnow_widget_is_reinitialized_after_livewire_updates(): void
    {
        $view = file_get_contents(resource_path('views/livewire/front/checkout.blade.php'));

        $this->assertStringContainsString("livewire.hook('message.processed', initBoxNowMap)", $view);
        $this->assertStringContainsString('new WeakSet()', $view);
        $this->assertStringContainsString("script.dataset.boxnowWidget = '1'", $view);
    }
}


class CheckoutWithAvailableMethods extends Checkout
{
    protected function availableShippingMethods(?array $cart = null): Collection
    {
        return collect([
            (object) ['code' => 'boxnow'],
        ]);
    }

    protected function availablePaymentMethods(?array $cart = null): Collection
    {
        return collect([
            (object) ['code' => 'corvus'],
        ]);
    }
}
