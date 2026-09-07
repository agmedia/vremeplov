<?php

namespace App\Support;

use App\Models\User;

class ProductReviewBackfillAccess
{
    public static function allows(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $allowedEmail = mb_strtolower(trim((string) config('reviews.backfill_admin_email')));
        $userEmail = mb_strtolower(trim((string) $user->email));

        return $allowedEmail !== '' && hash_equals($allowedEmail, $userEmail);
    }
}
