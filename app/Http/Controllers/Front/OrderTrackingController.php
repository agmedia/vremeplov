<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Back\Orders\Order;

class OrderTrackingController extends Controller
{
    public function __invoke(Order $order)
    {
        return view('front.checkout.tracking', compact('order'));
    }
}
