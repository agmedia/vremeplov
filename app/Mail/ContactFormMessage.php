<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ContactFormMessage extends Mailable
{

    use Queueable, SerializesModels;

    /**
     * @var array
     */
    private $data;


    /**
     * Create a new message instance.
     *
     * @param $contact
     */
    public function __construct($data)
    {
        $this->data = $data;
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Novi upit s kontakt forme — Vremeplov')
            ->replyTo($this->data['email'], $this->data['name'])
            ->view('emails.contact-form')
            ->with(['data' => $this->data]);
    }
}
