<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContractTerminationConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->subject('Potvrda primitka izjave o raskidu — Vremeplov')
            ->view('emails.contract-termination-confirmation')
            ->with(['data' => $this->data]);
    }
}
