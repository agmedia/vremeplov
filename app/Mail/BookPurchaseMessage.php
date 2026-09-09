<?php

namespace App\Mail;

use App\Models\BookPurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookPurchaseMessage extends Mailable
{
    use Queueable, SerializesModels;

    public $purchase;

    public function __construct(BookPurchaseRequest $purchase)
    {
        $this->purchase = $purchase;
    }

    public function build()
    {
        return $this->subject('Nova prijava za otkup knjiga — Vremeplov')
            ->replyTo($this->purchase->email, $this->purchase->full_name)
            ->view('emails.book-purchase');
    }
}
