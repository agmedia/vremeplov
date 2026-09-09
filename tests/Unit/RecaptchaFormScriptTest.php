<?php

namespace Tests\Unit;

use Tests\TestCase;

class RecaptchaFormScriptTest extends TestCase
{
    public function test_form_script_guards_pending_requests_and_restores_submit_controls(): void
    {
        config(['services.recaptcha.sitekey' => 'test-site-key']);

        $html = view('front.layouts.partials.recaptcha-js', [
            'action' => 'newsletter',
            'fieldId' => 'recaptcha_newsletter',
            'formId' => 'newsletter-form',
        ])->render();

        $this->assertStringContainsString('document.getElementById("recaptcha_newsletter")', $html);
        $this->assertStringContainsString('document.getElementById("newsletter-form")', $html);
        $this->assertStringContainsString(
            'window.VremeplovRecaptcha.execute("newsletter", "recaptcha_newsletter")',
            $html
        );
        $this->assertStringContainsString("form.dataset.recaptchaPending === '1'", $html);
        $this->assertStringContainsString("form.dataset.recaptchaPending = '1'", $html);
        $this->assertStringContainsString(
            'form.querySelectorAll(\'button[type="submit"], input[type="submit"]\')',
            $html
        );
        $this->assertStringContainsString('control.disabled = true', $html);
        $this->assertStringContainsString('control.disabled = disabledStates[index]', $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'delete form.dataset.recaptchaPending'));
        $this->assertStringContainsString('form.requestSubmit(submitter)', $html);
    }
}
