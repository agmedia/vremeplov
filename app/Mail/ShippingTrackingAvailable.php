<?php

namespace App\Mail;

use App\Models\Back\Orders\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ShippingTrackingAvailable extends Mailable
{
    use Queueable, SerializesModels;

    /** @var Order */
    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function build()
    {
        return $this->subject('Vaša pošiljka je poslana - Antikvarijat Vremeplov')
            ->view('emails.shipping-tracking-available');
    }
}
