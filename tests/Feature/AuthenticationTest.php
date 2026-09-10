<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\GoogleOidcService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('id="signin-modal"', false);
        $response->assertSee('Prijava');
        $response->assertSee('Registracija');
        $response->assertSee('class="password-visibility-toggle"', false);
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_google_login_button_is_shown_when_google_login_is_configured()
    {
        $this->configureGoogleLogin();

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Nastavi s Google računom');
        $response->assertSee(route('google.login.redirect', ['redirect' => url('/login')]), false);
    }

    public function test_google_login_redirect_uses_state_nonce_and_pkce()
    {
        $this->configureGoogleLogin();

        $response = $this->get(route('google.login.redirect', [
            'redirect' => url('/kosarica'),
        ]));

        $location = $response->headers->get('Location');
        $query = [];
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('accounts.google.com', parse_url($location, PHP_URL_HOST));
        $this->assertSame('test.apps.googleusercontent.com', $query['client_id'] ?? null);
        $this->assertSame('S256', $query['code_challenge_method'] ?? null);
        $this->assertNotEmpty($query['state'] ?? null);
        $this->assertNotEmpty($query['nonce'] ?? null);
        $this->assertNotEmpty($query['code_challenge'] ?? null);
        $this->assertSame(url('/kosarica'), session('google_login_transaction.redirect'));
    }

    public function test_existing_customer_can_log_in_with_verified_google_email()
    {
        $this->configureGoogleLogin();

        $user = User::factory()->create(['email' => 'kupac@gmail.com']);
        $user->details()->create([
            'fname' => '',
            'lname' => '',
            'address' => '',
            'zip' => '',
            'city' => '',
            'state' => '',
            'phone' => '',
            'avatar' => 'media/avatars/avatar1.jpg',
            'bio' => '',
            'social' => '',
            'role' => 'customer',
            'status' => 1,
        ]);

        $oidc = Mockery::mock(GoogleOidcService::class);
        $oidc->shouldReceive('exchangeAuthorizationCode')
            ->once()
            ->with(
                'test.apps.googleusercontent.com',
                'test-secret',
                route('google.login.callback'),
                'authorization-code',
                'code-verifier'
            )
            ->andReturn(['id_token' => 'verified-token']);
        $oidc->shouldReceive('verifyIdToken')
            ->once()
            ->with('verified-token', 'test.apps.googleusercontent.com', 'nonce-value')
            ->andReturn([
                'sub' => 'google-user-id',
                'email' => 'kupac@gmail.com',
                'email_verified' => true,
            ]);
        $this->app->instance(GoogleOidcService::class, $oidc);

        $response = $this->withSession([
            'google_login_transaction' => [
                'state' => 'state-value',
                'nonce' => 'nonce-value',
                'code_verifier' => 'code-verifier',
                'redirect' => null,
                'created_at' => time(),
            ],
        ])->get(route('google.login.callback', [
            'state' => 'state-value',
            'code' => 'authorization-code',
        ]));

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('moj-racun'));
        $this->assertFalse(session()->has('google_login_transaction'));
    }

    public function test_customer_login_from_checkout_returns_to_checkout()
    {
        $user = User::factory()->create();
        $user->details()->create([
            'fname' => '',
            'lname' => '',
            'address' => '',
            'zip' => '',
            'city' => '',
            'state' => '',
            'phone' => '',
            'avatar' => 'media/avatars/avatar1.jpg',
            'bio' => '',
            'social' => '',
            'role' => 'customer',
            'status' => 1,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            '_redirect_to' => '/naplata?korak=podaci',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/naplata?korak=podaci');
    }

    private function configureGoogleLogin(): void
    {
        config([
            'services.google_login.enabled' => true,
            'services.google_login.client_id' => 'test.apps.googleusercontent.com',
            'services.google_login.client_secret' => 'test-secret',
        ]);
    }
}
