<?php

namespace Tests\Feature;

use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('id="signin-modal"', false);
        $response->assertSee('id="signup-tab"', false);
        $response->assertSee('Registrirajte se');
    }

    public function test_new_users_can_register()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_failed_recaptcha_returns_a_validation_error_instead_of_being_logged_in_as_a_response()
    {
        config([
            'services.recaptcha.bypass_local' => false,
            'services.recaptcha.sitekey' => 'test-site-key',
            'services.recaptcha.secret' => 'test-secret-key',
            'services.recaptcha.verify_url' => 'data://text/plain,' . rawurlencode(json_encode([
                'success' => true,
                'score' => 0.9,
                'action' => 'contact',
            ])),
        ]);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Rejected User',
            'email' => 'rejected@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
            'recaptcha' => 'wrong-action-token',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('recaptcha');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'rejected@example.com']);
    }
}
