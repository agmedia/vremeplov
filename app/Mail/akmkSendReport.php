<?php

namespace App\Mail;

use App\Models\Back\Orders\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class akmkSendReport extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Order
     */
    public $file;


    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->file = url('akmk_report.xlsx');
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Izvještaj Plava-Krava' )
            ->view('emails.akmk-send-report')
            ->attach(storage_path('akmk_report.xlsx'));
    }
}
