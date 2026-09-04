<?php

namespace App\Mail;

use App\Models\ProductReviewInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProductReviewRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public ProductReviewInvitation $invitation;

    public string $reviewUrl;

    public function __construct(ProductReviewInvitation $invitation, string $reviewUrl)
    {
        $this->invitation = $invitation;
        $this->reviewUrl = $reviewUrl;
    }

    public function build()
    {
        return $this
            ->subject('Kako su vam se svidjele knjige iz narudžbe #' . $this->invitation->order_id . '?')
            ->view('emails.product-review-request');
    }
}
