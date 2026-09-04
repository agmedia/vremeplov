<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WishlistArrived extends Mailable
{
    use Queueable, SerializesModels;

    private $product;

    public function __construct($product)
    {
        $this->product = $product;
    }

    public function build()
    {
        return $this->subject('Artikl s vaše liste želja ponovno je dostupan — Vremeplov')
            ->view('emails.wishlist-arrived')
            ->with(['product' => $this->product]);
    }
}
