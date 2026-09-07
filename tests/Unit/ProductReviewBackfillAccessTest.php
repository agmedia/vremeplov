<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\ProductReviewBackfillAccess;
use Tests\TestCase;

class ProductReviewBackfillAccessTest extends TestCase
{
    public function test_only_configured_master_email_has_access(): void
    {
        config(['reviews.backfill_admin_email' => 'tomislav@agmedia.hr']);

        $master = new User(['email' => ' Tomislav@AGMEDIA.HR ']);
        $otherAdmin = new User(['email' => 'admin@example.test']);

        $this->assertTrue(ProductReviewBackfillAccess::allows($master));
        $this->assertFalse(ProductReviewBackfillAccess::allows($otherAdmin));
        $this->assertFalse(ProductReviewBackfillAccess::allows(null));
    }

    public function test_empty_configuration_never_grants_access(): void
    {
        config(['reviews.backfill_admin_email' => '']);

        $this->assertFalse(ProductReviewBackfillAccess::allows(
            new User(['email' => 'tomislav@agmedia.hr'])
        ));
    }
}
