<?php

namespace App\Mail;

use App\Models\Back\Orders\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Order
     */
    public $order;

    /**
     * @var object
     */
    public $status;

    /**
     * @var string|null
     */
    public $comment;

    /**
     * @param Order       $order
     * @param object      $status
     * @param string|null $comment
     */
    public function __construct(Order $order, object $status, ?string $comment = null)
    {
        $this->order = $order;
        $this->status = $status;
        $this->comment = $comment;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Novi status narudžbe #' . $this->order->id . ': ' . $this->status->title)
            ->view('emails.order-status-changed');
    }
}
