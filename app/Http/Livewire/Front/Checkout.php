<?php

namespace App\Http\Livewire\Front;

use App\Helpers\Country;
use App\Helpers\Currency;
use App\Helpers\Helper;
use App\Helpers\Session\CheckoutSession;
use App\Models\Back\Settings\Settings;
use App\Models\Front\AgCart;
use App\Models\Front\Checkout\GeoZone;
use App\Models\Front\Checkout\PaymentMethod;
use App\Models\Front\Checkout\ShippingMethod;
use App\Models\TagManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

class Checkout extends Component
{

    /**
     * @var string
     */
    public $step = '';

    /**
     * @var string
     */
    public $is_free_shipping = '';

    /**
     * @var array
     */
    public $login = [
        'email' => '',
        'pass' => '',
        'remember' => false
    ];

    /**
     * @var string[]
     */
    public $address = [
        'fname' => '',
        'lname' => '',
        'email' => '',
        'phone' => '',
        'address' => '',
        'city' => '',
        'zip' => '',
        'company' => '',
        'oib' => '',
        'state' => '',
    ];

    /**
     * @var string
     */
    public $shipping = '';

    /**
     * @var string
     */
    public $payment = '';

    /**
     * @var int|bool
     */
    public $secondary_price = false;

    /**
     * @var array
     */
    public $gdl = [];

    public $gdl_event = '';

    public $gdl_shipping = false;

    public $gdl_payment = false;

    protected $cart = false;

    /**
     * @var string[]
     */
    protected $address_rules = [
        'address.fname' => 'required',
        'address.lname' => 'required',
        'address.email' => 'required|email',
        'address.phone' => 'required',
        'address.address' => 'required',
        'address.city' => 'required',
        'address.zip' => 'required',
        'address.state' => 'required',
    ];

    /**
     * @var string[]
     */
    protected $shipping_rules = [
        'shipping' => 'required',
    ];

    /**
     * @var string[]
     */
    protected $payment_rules = [
        'payment' => 'required',
    ];

    /**
     * @var \string[][]
     */
    protected $queryString = ['step' => ['except' => '']];


    /**
     *
     */
    public function mount()
    {
        if (CheckoutSession::hasAddress()) {
            $this->setAddress(CheckoutSession::getAddress());
        } else {
            $this->setAddress();
        }

        if (CheckoutSession::hasShipping()) {
            $this->shipping = CheckoutSession::getShipping();
        } else {
            $this->selectShipping('gls');
        }

        if (CheckoutSession::hasPayment()) {
            $this->payment = CheckoutSession::getPayment();
        } else {
            $this->selectPayment('corvus');
        }

        $this->secondary_price = Currency::secondary() ? Currency::secondary()->value : false;

        $this->checkCart();

        $this->changeStep($this->step);
    }


    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authUser()
    {
        $validated = Validator::make([
            'email' => $this->login['email'],
            'password' => $this->login['pass'],
        ],[
            'email' => ['required', 'email'],
            'password' => ['required'],
        ])->validate();

        if (Auth::attempt($validated, $this->login['remember'])) {
            session()->regenerate();
            $this->setAddress();

            session()->flash('login_success', 'Uspješno ste se prijavili na vaš račun...');

            return redirect(request()->header('Referer'));
        }

        session()->flash('error', 'Upisani podaci ne odgovaraju našim korisnicima...');
    }


    /**
     * @param string $step
     */
    public function changeStep(string $step = '')
    {
        $this->checkCart();

        if (in_array($step, ['', 'podaci']) && $this->cart) {
            $this->gdl = TagManager::getGoogleCartDataLayer($this->cart->get());
            $this->gdl_event = 'begin_checkout';
            $this->gdl_shipping = false;
            $this->gdl_payment = false;
        }
        // Podaci
        if ($step == '') {
            $step = 'podaci';

            if (CheckoutSession::hasStep()) {
                $step = CheckoutSession::getStep();
            }
        }

        // Dostava
        if (in_array($step, ['dostava', 'placanje']) && $this->cart) {
            $this->setAddress($this->address);
            $this->validate($this->address_rules);

            if ($step == 'dostava' && $this->shipping != '') {
                $this->checkShipping($this->shipping);
                $this->gdl = TagManager::getGoogleCartDataLayer($this->cart->get());
                $this->gdl_event = 'add_shipping_info';
            }

            if ($step == 'placanje' && $this->payment != '') {
                $this->checkPayment($this->payment);
                $this->gdl = TagManager::getGoogleCartDataLayer($this->cart->get());
                $this->gdl_event = 'add_payment_info';
            }
        }

        // Dostava
        if ($step == 'dostava') {
            $this->validate($this->address_rules);
        }

        // Plaćanje
        if ($step == 'placanje') {
            $this->validate($this->shipping_rules);
        }

        $this->step = $step;

        CheckoutSession::setStep($step);
    }


    /**
     * @param string $state
     */
    public function stateSelected($state)
    {
        $this->setAddress(['state' => $state], true);

        CheckoutSession::forgetShipping();
        $this->shipping = '';
        CheckoutSession::forgetPayment();
        $this->payment = '';

        $this->render();
    }


    /**
     * @param string $shipping
     */
    public function selectShipping(string $shipping)
    {
        $this->shipping = $shipping;

        $this->checkShipping($shipping);

        CheckoutSession::setShipping($shipping);

       // return redirect()->route('naplata', ['step' => 'dostava']);
    }


    /**
     * @param string $payment
     */
    public function selectPayment(string $payment)
    {

        $this->payment = $payment;

        $this->checkPayment($payment);

        CheckoutSession::setPayment($payment);
    }


    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function render()
    {
        $geo = (new GeoZone())->findState($this->address['state'] ?: 'Croatia');

        if ( ! isset($geo->id)) {
            $geo->id = 1;
        }

        $this->checkCart();
        $cart = $this->cart ? $this->cart->get() : [];

        return view('livewire.front.checkout', [
            'shippingMethods' => (new ShippingMethod())->findGeo($geo->id)->checkCart($cart)->resolve(),
            'paymentMethods' => (new PaymentMethod())->findGeo($geo->id)->checkShipping($this->shipping)->checkCart($cart)->resolve()->sortBy('sort_order'),
            'countries' => Country::list()
        ]);
    }


    /**
     * @param array $value
     *
     * @return array
     */
    private function setAddress(array $value = [], bool $only_state = false)
    {
        if ( ! empty($value)) {
            $value['state'] = isset($value['state']) ? $value['state'] : 'Croatia';

            if ($only_state) {
                $this->address['state'] = $value['state'];

            } else {
                $this->address = [
                    'fname' => $value['fname'],
                    'lname' => $value['lname'],
                    'email' => $value['email'],
                    'phone' => $value['phone'],
                    'address' => $value['address'],
                    'city' => $value['city'],
                    'company' => $value['company'],
                    'oib' => $value['oib'],
                    'zip' => $value['zip'],
                    'state' => $value['state'],
                ];
            }
        } else {
            if (auth()->user()) {
                $this->address = [
                    'fname' => auth()->user()->details->fname,
                    'lname' => auth()->user()->details->lname,
                    'email' => auth()->user()->email,
                    'phone' => auth()->user()->details->phone,
                    'address' => auth()->user()->details->address,
                    'city' => auth()->user()->details->city,
                    'company' => auth()->user()->details->company,
                    'oib' => auth()->user()->details->oib,
                    'zip' => auth()->user()->details->zip,
                    'state' => auth()->user()->details->state
                ];
            }
        }

        CheckoutSession::setAddress($this->address);

        /*CheckoutSession::setGeoZone(
            GeoZone::findState($this->address['state'])
        );*/

        //dd($this->address);

        return $this->address;
    }


    /**
     * @param string $shipping
     *
     * @return void
     */
    private function checkShipping(string $shipping): void
    {
        if ($shipping == 'pickup') {
            $this->gdl_shipping = 'osobno preuzimanje';
        } else {
            $this->gdl_shipping = 'dostava';
        }
    }


    /**
     * @param string $payment
     *
     * @return void
     */
    private function checkPayment(string $payment): void
    {
        if ($payment == 'bank') {
            $this->gdl_payment = 'uplatnica';
        } elseif ($payment == 'cod') {
            $this->gdl_payment = 'pouzeće';
        } else {
            $this->gdl_payment = 'kartica';
        }
    }


    /**
     * @return void
     */
    private function checkCart(): void
    {
        if (session()->has(config('session.cart'))) {
            $this->cart = new AgCart(session(config('session.cart')));
        }
    }
}
