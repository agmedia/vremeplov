<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContractTerminationMessage extends Mailable
{
    use Queueable, SerializesModels;

    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->subject('Izjava o jednostranom raskidu — narudžba ' . $this->data['order_number'])
            ->replyTo($this->data['email'], $this->data['full_name'])
            ->view('emails.contract-termination')
            ->with(['data' => $this->data]);
    }
}
