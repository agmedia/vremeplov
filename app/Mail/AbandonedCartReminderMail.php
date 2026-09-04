<?php

namespace App\Mail;

use App\Models\Back\Orders\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AbandonedCartReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $recoveryUrl;
    public $sequence;

    public function __construct(Order $order, string $recoveryUrl, int $sequence)
    {
        $this->order = $order;
        $this->recoveryUrl = $recoveryUrl;
        $this->sequence = $sequence;
    }

    public function build()
    {
        $subject = $this->sequence === 1
            ? 'Vaša kupnja u Vremeplovu čeka dovršetak'
            : 'Želite li dovršiti svoju kupnju u Vremeplovu?';

        return $this->subject($subject)->view('emails.abandoned-cart-reminder');
    }
}
