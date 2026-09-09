<?php

namespace Tests\Feature;

use App\Helpers\Session\CheckoutSession;
use App\Http\Livewire\Front\Checkout;
use Tests\TestCase;

class CheckoutAddressAutofillTest extends TestCase
{
    public function test_entering_postal_code_fills_the_city_and_session(): void
    {
        $checkout = new Checkout();
        $checkout->address['state'] = 'Croatia';
        $checkout->address['zip'] = '51000';

        $checkout->updatedAddress('51000', 'zip');

        $this->assertSame('Rijeka', $checkout->address['city']);
        $this->assertSame('51000', CheckoutSession::getAddress()['zip']);
        $this->assertSame('Rijeka', CheckoutSession::getAddress()['city']);
    }

    public function test_entering_city_fills_the_postal_code_and_session(): void
    {
        $checkout = new Checkout();
        $checkout->address['state'] = 'Croatia';
        $checkout->address['city'] = 'Osijek';

        $checkout->updatedAddress('Osijek', 'city');

        $this->assertSame('31000', $checkout->address['zip']);
        $this->assertSame('Osijek', CheckoutSession::getAddress()['city']);
    }

    public function test_foreign_address_is_not_replaced_with_a_croatian_place(): void
    {
        $checkout = new Checkout();
        $checkout->address['state'] = 'Germany';
        $checkout->address['zip'] = '10000';
        $checkout->address['city'] = 'Berlin';

        $checkout->updatedAddress('10000', 'zip');

        $this->assertSame('10000', $checkout->address['zip']);
        $this->assertSame('Berlin', $checkout->address['city']);
    }
}
