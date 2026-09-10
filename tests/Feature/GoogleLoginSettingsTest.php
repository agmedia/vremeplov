<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleLoginSettingsService;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GoogleLoginSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_configure_google_login_and_secret_is_encrypted()
    {
        $admin = User::factory()->create();
        Bouncer::assign('admin')->to($admin);

        $page = $this->actingAs($admin)->get(route('google-login.edit'));

        $page->assertOk();
        $page->assertSee(route('google.login.callback'));

        $response = $this->actingAs($admin)->patch(route('google-login.update'), [
            'enabled' => '1',
            'client_id' => 'test.apps.googleusercontent.com',
            'client_secret' => 'super-secret-value',
        ]);

        $response->assertRedirect(route('google-login.edit'));

        $stored = DB::table('settings')
            ->where('code', 'auth')
            ->where('key', 'google_login')
            ->value('value');

        $this->assertIsString($stored);
        $this->assertStringNotContainsString('super-secret-value', $stored);
        $this->assertSame('super-secret-value', app(GoogleLoginSettingsService::class)->get()['client_secret']);
        $this->assertTrue(app(GoogleLoginSettingsService::class)->enabled());
    }

    public function test_google_login_settings_are_restricted_to_administrators()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('google-login.edit'))
            ->assertForbidden();
    }
}
