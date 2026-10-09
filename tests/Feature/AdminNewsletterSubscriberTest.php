<?php

namespace Tests\Feature;

use App\Models\Back\Marketing\NewsletterSubscriber;
use App\Models\User;
use App\Services\Mailchimp\MailchimpConnectionSettings;
use App\Services\Mailchimp\NewsletterSyncService;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminNewsletterSubscriberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mailchimp.enabled' => false,
            'services.mailchimp.api_key' => '',
            'services.mailchimp.server_prefix' => 'us1',
            'services.mailchimp.audience_id' => '',
            'services.mailchimp.webhook_token' => '',
            'services.mailchimp.webhook_signing_secret' => '',
        ]);
        Http::fake();
    }

    public function test_administrator_can_view_search_and_filter_newsletter_subscribers(): void
    {
        $admin = $this->userWithRole('admin');

        $this->subscriber('aktivna@example.test', true, 'homepage');
        $this->subscriber('neaktivna@example.test', false, 'import');

        $this->actingAs($admin)
            ->get(route('newsletter-subscribers.index'))
            ->assertOk()
            ->assertSee('Newsletter prijave')
            ->assertSee('aktivna@example.test')
            ->assertSee('neaktivna@example.test');

        $this->actingAs($admin)
            ->get(route('newsletter-subscribers.index', [
                'search' => 'aktivna@',
                'status' => 'active',
            ]))
            ->assertOk()
            ->assertSee('aktivna@example.test')
            ->assertDontSee('neaktivna@example.test');
    }

    public function test_administrator_can_export_only_filtered_newsletter_subscribers(): void
    {
        $admin = $this->userWithRole('master');

        $this->subscriber('izvoz@example.test', true, 'homepage');
        $this->subscriber('formula@example.test', true, '=SUM(1+1)');
        $this->subscriber('preskoci@example.test', false, 'homepage');

        $response = $this->actingAs($admin)->get(route('newsletter-subscribers.export', [
            'status' => 'active',
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('newsletter-prijave-', (string) $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        $this->assertStringContainsString('izvoz@example.test', $content);
        $this->assertStringContainsString("'=SUM(1+1)", $content);
        $this->assertStringNotContainsString(';=SUM(1+1);', $content);
        $this->assertStringNotContainsString('preskoci@example.test', $content);
    }

    public function test_non_scalar_and_overlong_filters_are_safely_normalized(): void
    {
        $admin = $this->userWithRole('admin');
        $this->subscriber('normalna@example.test', true, 'homepage');

        $this->actingAs($admin)
            ->get(route('newsletter-subscribers.index', [
                'search' => ['unexpected'],
                'status' => ['active'],
                'source' => ['homepage'],
            ]))
            ->assertOk()
            ->assertSee('normalna@example.test');
    }

    public function test_non_administrator_cannot_view_newsletter_subscribers(): void
    {
        $editor = $this->userWithRole('editor');

        $this->actingAs($editor)
            ->get(route('newsletter-subscribers.index'))
            ->assertForbidden();
    }

    public function test_mailchimp_settings_are_saved_without_displaying_or_flashing_credentials(): void
    {
        $admin = $this->userWithRole('admin');
        $apiKey = 'private-mailchimp-key-us1';
        $signingSecret = 'private-signing-secret';

        $this->actingAs($admin)->put(route('newsletter-subscribers.mailchimp-settings', [
            'search' => 'reader', 'status' => 'active', 'source' => 'homepage',
        ]), [
            'enabled' => '1',
            'api_key' => $apiKey,
            'server_prefix' => 'us1',
            'audience_id' => 'audience123',
            'webhook_signing_secret' => $signingSecret,
        ])->assertRedirect(route('newsletter-subscribers.index', [
            'search' => 'reader', 'status' => 'active', 'source' => 'homepage',
        ]))->assertSessionHas('success')->assertSessionMissing('_old_input');

        $connection = app(MailchimpConnectionSettings::class)->connection();
        $this->assertSame($apiKey, $connection['api_key']);
        $this->assertSame($signingSecret, $connection['webhook_signing_secret']);

        $this->actingAs($admin)->get(route('newsletter-subscribers.index'))
            ->assertOk()
            ->assertSee('Povezivanje postavljeno')
            ->assertDontSee($apiKey)
            ->assertDontSee($signingSecret);

        $this->actingAs($admin)->put(route('newsletter-subscribers.mailchimp-settings'), [
            'enabled' => '1', 'api_key' => '', 'webhook_signing_secret' => '',
            'server_prefix' => 'us1', 'audience_id' => 'audience123',
        ])->assertRedirect()->assertSessionHas('success');

        $connection = app(MailchimpConnectionSettings::class)->connection();
        $this->assertSame($apiKey, $connection['api_key']);
        $this->assertSame($signingSecret, $connection['webhook_signing_secret']);

        $this->actingAs($admin)->put(route('newsletter-subscribers.mailchimp-settings'), [
            'enabled' => '0', 'server_prefix' => 'us1', 'audience_id' => 'audience123',
        ])->assertRedirect()->assertSessionHas('success');
        $this->actingAs($admin)->get(route('newsletter-subscribers.index'))
            ->assertOk()->assertSee('Povezivanje nije uključeno')
            ->assertDontSee($apiKey)->assertDontSee($signingSecret);
        Http::assertNothingSent();
    }

    public function test_invalid_settings_never_flash_credentials(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->put(route('newsletter-subscribers.mailchimp-settings'), [
            'enabled' => '1', 'api_key' => 'never-flash-this-api-key',
            'webhook_signing_secret' => 'never-flash-this-signing-key',
            'server_prefix' => 'invalid-server', 'audience_id' => 'audience123',
        ])->assertRedirect(route('newsletter-subscribers.index'))
            ->assertSessionHasErrors('server_prefix')
            ->assertSessionMissing('_old_input');

        $this->actingAs($admin)->get(route('newsletter-subscribers.index'))
            ->assertOk()
            ->assertSee('Povezivanje nije uključeno')
            ->assertDontSee('never-flash-this-api-key')
            ->assertDontSee('never-flash-this-signing-key');

        Http::assertNothingSent();
    }

    public function test_non_administrator_cannot_change_mailchimp_settings_or_start_sync(): void
    {
        $editor = $this->userWithRole('editor');

        $this->actingAs($editor)->put(route('newsletter-subscribers.mailchimp-settings'), [
            'enabled' => '0',
        ])->assertForbidden();

        $this->actingAs($editor)->post(route('newsletter-subscribers.sync'))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_settings_storage_failure_returns_a_safe_message_without_flashing_credentials(): void
    {
        $admin = $this->userWithRole('admin');
        $settings = $this->mock(MailchimpConnectionSettings::class);
        $settings->shouldReceive('publicSettings')->once()->andReturn(['key_configured' => false]);
        $settings->shouldReceive('save')->once()->andThrow(new \RuntimeException('private-storage-detail'));

        $this->actingAs($admin)->put(route('newsletter-subscribers.mailchimp-settings'), [
            'enabled' => '0', 'api_key' => 'private-api-key', 'webhook_signing_secret' => 'private-signing-key',
        ])->assertRedirect(route('newsletter-subscribers.index'))
            ->assertSessionHas('error', 'Mailchimp postavke nije moguće spremiti. Pokušajte ponovno.')
            ->assertSessionMissing('_old_input');
        Http::assertNothingSent();
    }

    public function test_mailchimp_overview_distinguishes_sync_confirmation_and_attention_without_raw_errors(): void
    {
        $admin = $this->userWithRole('admin');
        $this->subscriber('synced@example.test', true, 'homepage', [
            'mailchimp_sync_status' => 'synced', 'mailchimp_synced_at' => now(),
        ]);
        $this->subscriber('pending@example.test', true, 'homepage');
        $this->subscriber('confirmation@example.test', true, 'homepage', [
            'mailchimp_sync_status' => 'pending_confirmation',
        ]);
        $this->subscriber('error@example.test', true, 'homepage', [
            'mailchimp_sync_status' => 'error', 'mailchimp_last_error' => 'remote_http_401_private_detail',
        ]);
        $this->subscriber('preserved@example.test', true, 'homepage', ['mailchimp_sync_status' => 'preserved']);
        $this->subscriber('inactive@example.test', false, 'homepage', ['mailchimp_sync_status' => 'error']);
        $this->subscriber('no-consent@example.test', true, 'homepage', [
            'gdpr' => false, 'mailchimp_sync_status' => 'error',
        ]);

        $this->actingAs($admin)->get(route('newsletter-subscribers.index'))
            ->assertOk()
            ->assertViewHas('mailchimpStatistics', ['synced' => 1, 'pending' => 2, 'error' => 1])
            ->assertSee('Usklađeno')
            ->assertSee('Čeka potvrdu')
            ->assertSee('Sačuvan postojeći status')
            ->assertSee('Bez privole')
            ->assertDontSee('remote_http_401_private_detail');

        Http::assertNothingSent();
    }

    public function test_manual_sync_respects_filters_consent_existing_states_and_the_25_contact_batch(): void
    {
        $admin = $this->userWithRole('admin');
        $expectedIds = [];
        for ($i = 0; $i < 30; $i++) {
            $subscriber = $this->subscriber('target' . $i . '@example.test', true, 'homepage');
            if ($i < 25) {
                $expectedIds[] = $subscriber->id;
            }
        }
        $this->subscriber('target-inactive@example.test', false, 'homepage');
        $this->subscriber('target-no-consent@example.test', true, 'homepage', ['gdpr' => false]);
        $this->subscriber('target-other-source@example.test', true, 'import');
        $this->subscriber('other-search@example.test', true, 'homepage');
        $this->subscriber('target-synced@example.test', true, 'homepage', ['mailchimp_sync_status' => 'synced']);
        $this->subscriber('target-preserved@example.test', true, 'homepage', ['mailchimp_sync_status' => 'preserved']);

        $sync = $this->mock(NewsletterSyncService::class);
        $sync->shouldReceive('connectionStatus')->once()->andReturn($this->readyConnection());
        $sync->shouldReceive('syncIds')->once()->with($expectedIds, 25)->andReturn([
            'processed' => 25, 'synced' => 25,
        ]);
        $filters = ['search' => 'target', 'status' => 'active', 'source' => 'homepage'];

        $this->actingAs($admin)->post(route('newsletter-subscribers.sync', $filters))
            ->assertRedirect(route('newsletter-subscribers.index', $filters))
            ->assertSessionHas('success');
        Http::assertNothingSent();
    }

    public function test_disabled_connection_does_not_sync_any_contacts(): void
    {
        $admin = $this->userWithRole('admin');
        $this->subscriber('pending@example.test', true, 'homepage');

        $this->actingAs($admin)->post(route('newsletter-subscribers.sync', ['status' => 'active']))
            ->assertRedirect(route('newsletter-subscribers.index', ['status' => 'active']))
            ->assertSessionHas('warning', 'Povezivanje s Mailchimpom je isključeno. Uključite ga u postavkama.');

        Http::assertNothingSent();
    }

    public function test_manual_sync_explains_a_retry_without_exposing_remote_details(): void
    {
        $admin = $this->userWithRole('admin');
        $subscriber = $this->subscriber('pending@example.test', true, 'homepage');
        $sync = $this->mock(NewsletterSyncService::class);
        $sync->shouldReceive('connectionStatus')->once()->andReturn($this->readyConnection());
        $sync->shouldReceive('syncIds')->once()->with([$subscriber->id], 25)->andReturn([
            'processed' => 1, 'retry' => 1, 'error_code' => 'private_remote_detail',
        ]);

        $this->actingAs($admin)->post(route('newsletter-subscribers.sync'))
            ->assertRedirect(route('newsletter-subscribers.index'))
            ->assertSessionHas('warning', function ($message) {
                return str_contains($message, 'Dio prijava čeka provjeru ili novi pokušaj')
                    && ! str_contains($message, 'private_remote_detail');
            });
        Http::assertNothingSent();
    }

    private function readyConnection(): array
    {
        return ['enabled' => true, 'configured' => true, 'available' => true, 'ready' => true];
    }

    private function subscriber(string $email, bool $active, string $source, array $attributes = []): NewsletterSubscriber
    {
        return NewsletterSubscriber::query()->create(array_merge([
            'email' => $email,
            'source' => $source,
            'gdpr' => true,
            'status' => $active,
            'subscribed_at' => now(),
        ], $attributes));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $storedRole = Bouncer::role()->create([
            'name' => $role,
            'title' => ucfirst($role),
        ]);
        Bouncer::assign($storedRole)->to($user);
        Bouncer::refresh();

        return $user;
    }
}
