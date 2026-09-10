<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StorefrontContentSettingsService;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontContentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_deployment_migration_seeds_the_current_storefront_content(): void
    {
        $setting = DB::table('settings')
            ->where('code', 'app')
            ->where('key', 'storefront_content')
            ->first();

        $this->assertNotNull($setting);
        $this->assertTrue((bool) $setting->json);
        $this->assertSame(
            'Besplatna dostava U RH za narudžbe iznad 70 €',
            json_decode($setting->value, true)[0]['announcement_text']
        );
    }

    public function test_administrator_can_update_the_announcement_and_footer_content(): void
    {
        $admin = User::factory()->create();
        Bouncer::assign('admin')->to($admin);

        $this->actingAs($admin)
            ->get(route('application.settings'))
            ->assertOk()
            ->assertSee('Sadržaj zaglavlja i footera')
            ->assertSee('Tekst gornje obavijesti')
            ->assertSee('Radno vrijeme Pon-Pet');

        $payload = [
            'announcement_text' => 'Nova obavijest',
            'footer_title' => 'Novi naziv',
            'footer_address' => 'Nova adresa 1',
            'footer_postal_code' => '10000',
            'footer_city' => 'Zagreb',
            'footer_phone' => '01 2345 678',
            'footer_weekday_hours' => 'Pon-Pet: 08 - 16h',
            'footer_saturday_hours' => 'Sub: zatvoreno',
            'instagram_url' => 'https://www.instagram.com/novi-profil',
            'facebook_url' => 'https://www.facebook.com/nova-stranica',
        ];

        $this->actingAs($admin)
            ->postJson(route('api.application.storefront-content.store'), $payload)
            ->assertOk()
            ->assertJson(['success' => 'Sadržaj zaglavlja i footera je spremljen.']);

        $stored = app(StorefrontContentSettingsService::class)->get();

        $this->assertSame('Nova obavijest', $stored['announcement_text']);
        $this->assertSame('Novi naziv', $stored['footer_title']);
        $this->assertSame('+38512345678', $stored['footer_phone_href']);
    }

    public function test_front_page_renders_saved_storefront_content(): void
    {
        DB::table('pages')->insert([
            'title' => 'Naslovnica',
            'slug' => 'homepage',
            'description' => '<p>Naslovnica</p>',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(StorefrontContentSettingsService::class)->save([
            'announcement_text' => 'Obavijest iz postavki',
            'footer_title' => 'Naslov iz postavki',
            'footer_address' => 'Adresa iz postavki',
            'footer_postal_code' => '21000',
            'footer_city' => 'Split',
            'footer_phone' => '021 555 444',
            'footer_weekday_hours' => 'Pon-Pet: 10 - 18h',
            'footer_saturday_hours' => 'Sub: 10 - 14h',
            'instagram_url' => null,
            'facebook_url' => null,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Obavijest iz postavki');
        $response->assertSee('Naslov iz postavki');
        $response->assertSee('Adresa iz postavki');
        $response->assertSee('Pon-Pet: 10 - 18h');
        $response->assertDontSee('btn-social bs-light bg-primary bs-instagram', false);
    }
}
