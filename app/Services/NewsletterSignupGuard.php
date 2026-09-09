<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class NewsletterSignupGuard
{
    public const ALLOWED = 'allowed';
    public const TOO_FAST = 'too_fast';
    public const INVALID = 'invalid';

    private const MINIMUM_SECONDS = 2;
    private const MAXIMUM_SECONDS = 7200;

    public function issueToken(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    public function honeypotIsFilled($value): bool
    {
        if (is_array($value) || is_object($value)) {
            return true;
        }

        return trim((string) $value) !== '';
    }

    public function timingResult($token): string
    {
        if (! is_string($token) || $token === '' || strlen($token) > 1024) {
            return self::INVALID;
        }

        try {
            $issuedAt = Crypt::decryptString($token);
        } catch (DecryptException $exception) {
            return self::INVALID;
        }

        if (! ctype_digit($issuedAt)) {
            return self::INVALID;
        }

        $age = now()->getTimestamp() - (int) $issuedAt;

        if ($age < self::MINIMUM_SECONDS) {
            return self::TOO_FAST;
        }

        if ($age > self::MAXIMUM_SECONDS) {
            return self::INVALID;
        }

        return self::ALLOWED;
    }
}
