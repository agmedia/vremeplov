<?php

namespace App\Mail;

use App\Models\Back\Orders\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class StatusReady extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Order
     */
    public $order;


    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Narudžba spremna za preuzimanje - Antikvarijat Vremeplov' )
            ->view('emails.status-ready');
    }
}
