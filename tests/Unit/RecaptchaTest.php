<?php

namespace Tests\Unit;

use App\Helpers\Recaptcha;
use Tests\TestCase;

class RecaptchaTest extends TestCase
{
    public function test_it_accepts_a_valid_score_with_the_expected_action(): void
    {
        $this->fakeVerificationResponse([
            'success' => true,
            'score' => 0.9,
            'action' => 'newsletter',
        ]);

        $this->assertTrue(
            (new Recaptcha())->check(['recaptcha' => 'valid-token'], 'newsletter')->ok()
        );
    }

    public function test_it_rejects_a_token_issued_for_a_different_action(): void
    {
        $this->fakeVerificationResponse([
            'success' => true,
            'score' => 0.9,
            'action' => 'contact',
        ]);

        $this->assertFalse(
            (new Recaptcha())->check(['recaptcha' => 'valid-token'], 'newsletter')->ok()
        );
    }

    public function test_it_fails_closed_when_recaptcha_is_not_configured(): void
    {
        config([
            'services.recaptcha.bypass_local' => false,
            'services.recaptcha.sitekey' => null,
            'services.recaptcha.secret' => null,
        ]);

        $this->assertFalse(
            (new Recaptcha())->check(['recaptcha' => 'token'], 'contact')->ok()
        );
    }

    private function fakeVerificationResponse(array $response): void
    {
        config([
            'services.recaptcha.bypass_local' => false,
            'services.recaptcha.sitekey' => 'test-site-key',
            'services.recaptcha.secret' => 'test-secret-key',
            'services.recaptcha.verify_url' => 'data://text/plain,' . rawurlencode(json_encode($response)),
        ]);
    }
}
