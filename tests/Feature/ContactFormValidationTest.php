<?php

namespace Tests\Feature;

use App\Mail\ContactFormMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        config([
            'mail.admin' => 'admin@example.test',
            'services.recaptcha.bypass_local' => true,
            'services.recaptcha.sitekey' => 'test-site-key',
            'services.recaptcha.secret' => 'test-secret-key',
        ]);
    }

    public function test_contact_page_uses_croatian_inline_validation_instead_of_browser_popovers(): void
    {
        $response = $this->get(route('kontakt'));

        $response->assertOk()
            ->assertSee('id="contact-form"', false)
            ->assertSee('data-inline-validation', false)
            ->assertSee('novalidate', false)
            ->assertSee('js/front-form-validation.js', false)
            ->assertSee('aria-describedby="cf-name-feedback"', false)
            ->assertSee('aria-describedby="cf-email-feedback"', false)
            ->assertSee('aria-describedby="cf-phone-feedback"', false)
            ->assertSee('aria-describedby="cf-message-feedback"', false)
            ->assertSee('id="cf-name-feedback"', false)
            ->assertSee('id="cf-email-feedback"', false)
            ->assertSee('id="cf-phone-feedback"', false)
            ->assertSee('id="cf-message-feedback"', false)
            ->assertSee('minlength="2"', false)
            ->assertSee('minlength="10"', false)
            ->assertSee('maxlength="5000"', false);

        $html = $response->getContent();
        $validatorPosition = strpos($html, 'js/front-form-validation.js');
        $recaptchaPosition = strpos($html, 'window.VremeplovRecaptcha.execute("contact", "recaptcha")');

        $this->assertNotFalse($validatorPosition);
        $this->assertNotFalse($recaptchaPosition);
        $this->assertLessThan($recaptchaPosition, $validatorPosition);
    }

    public function test_required_contact_fields_return_croatian_messages_and_send_no_mail(): void
    {
        $response = $this->from(route('kontakt'))->post(route('poruka'), []);

        $response->assertRedirect(route('kontakt'))
            ->assertSessionHasErrors([
                'name' => 'Upišite vaše ime.',
                'email' => 'Upišite e-mail adresu.',
                'phone' => 'Upišite broj telefona.',
                'message' => 'Upišite poruku.',
            ]);

        Mail::assertNothingSent();
    }

    public function test_malformed_contact_values_return_specific_croatian_messages(): void
    {
        $response = $this->from(route('kontakt'))->post(route('poruka'), [
            'name' => 'A',
            'email' => 'nije-email',
            'phone' => '12+34+56',
            'message' => 'Prekratko',
        ]);

        $response->assertRedirect(route('kontakt'))
            ->assertSessionHasErrors([
                'name' => 'Ime mora sadržavati najmanje 2 znaka.',
                'email' => 'Upišite ispravnu e-mail adresu.',
                'phone' => 'Upišite ispravan broj telefona (6–15 znamenki).',
                'message' => 'Poruka mora sadržavati najmanje 10 znakova.',
            ]);

        Mail::assertNothingSent();
    }

    public function test_array_values_are_rejected_as_validation_errors_instead_of_causing_a_server_error(): void
    {
        $response = $this->from(route('kontakt'))->post(route('poruka'), [
            'name' => ['Ana'],
            'email' => ['ana@example.test'],
            'phone' => ['091 123 4567'],
            'message' => ['Molim informacije o knjizi.'],
            'recaptcha' => ['malformed-token'],
        ]);

        $response->assertRedirect(route('kontakt'))
            ->assertSessionHasErrors([
                'name' => 'Ime mora biti tekst.',
                'email' => 'E-mail adresa mora biti tekst.',
                'phone' => 'Broj telefona mora biti tekst.',
                'message' => 'Poruka mora biti tekst.',
                'recaptcha' => 'Sigurnosna provjera nije valjana. Osvježite stranicu i pokušajte ponovno.',
            ]);

        Mail::assertNothingSent();
    }

    public function test_a_supported_international_phone_format_passes_validation(): void
    {
        $response = $this->from(route('kontakt'))->post(route('poruka'), [
            'name' => 'Ana Anić',
            'email' => 'ana@example.test',
            'phone' => '+385 (0)91 762-7441',
            'message' => 'Molim informacije o dostupnosti knjige.',
        ]);

        $response->assertRedirect(route('kontakt'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        Mail::assertSent(ContactFormMessage::class, 1);
    }
}
