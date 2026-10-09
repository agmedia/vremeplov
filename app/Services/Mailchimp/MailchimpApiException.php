<?php

namespace App\Services\Mailchimp;

use RuntimeException;

class MailchimpApiException extends RuntimeException
{
    public $errorCode;
    public $retryable;

    public function __construct(string $errorCode, bool $retryable = false)
    {
        // Never retain the upstream response, request, email or credentials.
        parent::__construct($errorCode);
        $this->errorCode = $errorCode;
        $this->retryable = $retryable;
    }
}
