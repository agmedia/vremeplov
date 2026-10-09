<?php

namespace Tests\Feature;

use App\Models\Back\Marketing\NewsletterSubscriber;
use App\Services\Mailchimp\MailchimpApi;
use App\Services\Mailchimp\MailchimpConnectionSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MailchimpConnectionSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test_callback_token_12345678901234567890123456789012';
    private const SIGNING_SECRET = 'test-mailchimp-signing-secret';
    private const AUDIENCE = 'audience123';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mailchimp.enabled' => false,
            'services.mailchimp.api_key' => '',
            'services.mailchimp.server_prefix' => '',
            'services.mailchimp.audience_id' => '',
            'services.mailchimp.webhook_token' => '',
            'services.mailchimp.webhook_signing_secret' => '',
            'database_capacity.guard.enabled' => false,
        ]);
        Http::fake();
    }

    public function test_secrets_are_encrypted_at_rest_and_omitted_from_public_settings(): void
    {
        $settings = app(MailchimpConnectionSettings::class);
        $this->assertTrue($settings->save([
            'enabled' => true,
            'api_key' => 'private-api-key-us7',
            'server_prefix' => 'us7',
            'audience_id' => self::AUDIENCE,
            'webhook_signing_secret' => self::SIGNING_SECRET,
        ]));

        $stored = $this->storedSettings();
        $connection = $settings->connection();
        foreach (['api_key', 'webhook_token', 'webhook_signing_secret'] as $key) {
            $this->assertArrayNotHasKey($key, $stored);
            $this->assertNotSame('', $connection[$key]);
            $this->assertStringNotContainsString($connection[$key], json_encode($stored));
            $this->assertSame($connection[$key], Crypt::decryptString($stored[$key . '_encrypted']));
        }
        $this->assertGreaterThanOrEqual(32, strlen($connection['webhook_token']));
        $this->assertSame([
            'enabled' => true,
            'server_prefix' => 'us7',
            'audience_id' => self::AUDIENCE,
            'key_configured' => true,
            'webhook_configured' => true,
            'signing_configured' => true,
        ], $settings->publicSettings());
        Http::assertNothingSent();
    }

    public function test_blank_secret_fields_preserve_saved_credentials_and_callback_token(): void
    {
        $settings = app(MailchimpConnectionSettings::class);
        $settings->save([
            'enabled' => true,
            'api_key' => 'original-api-key-us7',
            'server_prefix' => 'us7',
            'audience_id' => self::AUDIENCE,
            'webhook_signing_secret' => self::SIGNING_SECRET,
        ]);
        $original = $settings->connection();

        $settings->save([
            'enabled' => false,
            'api_key' => '  ',
            'webhook_signing_secret' => '',
        ]);

        $updated = $settings->connection();
        foreach (['api_key', 'webhook_token', 'webhook_signing_secret'] as $key) {
            $this->assertSame($original[$key], $updated[$key]);
        }
        $this->assertFalse($updated['enabled']);
        Http::assertNothingSent();
    }

    public function test_environment_configuration_is_used_when_no_saved_settings_exist(): void
    {
        $this->configureWebhook();
        config(['services.mailchimp.server_prefix' => '', 'services.mailchimp.api_key' => 'environment-api-key-us7']);

        $settings = app(MailchimpConnectionSettings::class);
        $connection = $settings->connection();

        $this->assertTrue($connection['enabled']);
        $this->assertSame('environment-api-key-us7', $connection['api_key']);
        $this->assertSame('us7', $connection['server_prefix']);
        $this->assertSame(self::TOKEN, $connection['webhook_token']);
        $this->assertSame(self::SIGNING_SECRET, $connection['webhook_signing_secret']);
        $this->assertSame(route('mailchimp.webhook', ['token' => self::TOKEN]), $settings->webhookUrl());
        Http::assertNothingSent();
    }

    public function test_invalid_saved_ciphertext_never_falls_back_to_environment_credentials(): void
    {
        $this->configureWebhook();
        $this->writeStoredSettings([
            'enabled' => true,
            'api_key_encrypted' => 'invalid-ciphertext',
            'webhook_token_encrypted' => 'invalid-ciphertext',
            'webhook_signing_secret_encrypted' => 'invalid-ciphertext',
        ]);

        $connection = app(MailchimpConnectionSettings::class)->connection();

        $this->assertSame('', $connection['api_key']);
        $this->assertSame('', $connection['webhook_token']);
        $this->assertSame('', $connection['webhook_signing_secret']);
        $this->assertFalse((bool) app(MailchimpApi::class)->configured($connection));
        $this->assertNull(app(MailchimpConnectionSettings::class)->webhookUrl());
        Http::assertNothingSent();
    }

    public function test_explicitly_empty_saved_secrets_override_environment_credentials(): void
    {
        $this->configureWebhook();
        $this->writeStoredSettings([
            'enabled' => false,
            'api_key_encrypted' => '',
            'webhook_token_encrypted' => '',
            'webhook_signing_secret_encrypted' => '',
        ]);

        $connection = app(MailchimpConnectionSettings::class)->connection();

        $this->assertFalse($connection['enabled']);
        $this->assertSame('', $connection['api_key']);
        $this->assertSame('', $connection['webhook_token']);
        $this->assertSame('', $connection['webhook_signing_secret']);
    }

    public function test_callback_get_checks_the_token_without_requiring_an_event_signature(): void
    {
        $this->configureWebhook();

        $this->get($this->webhookUrl())->assertOk()->assertSee('OK');
        $this->get($this->webhookUrl(str_repeat('x', 48)))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_unsigned_post_is_rejected_when_signing_is_configured(): void
    {
        $this->configureWebhook();
        $subscriber = $this->subscriber();

        $this->post($this->webhookUrl(), $this->payload('unsubscribe'))->assertForbidden();

        $this->assertTrue($subscriber->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_signed_form_post_updates_local_unsubscribe_without_remote_writes(): void
    {
        $this->configureWebhook();
        $subscriber = $this->subscriber();
        $this->fakeRemoteMember('unsubscribed');

        $this->signedPost($this->payload('unsubscribe'))->assertOk();

        $subscriber->refresh();
        $this->assertFalse($subscriber->status);
        $this->assertSame('unsubscribed', $subscriber->mailchimp_member_status);
        $this->assertOnlyReadOnlyRequests();
    }

    public function test_valid_signature_does_not_bypass_the_callback_token(): void
    {
        $this->configureWebhook();
        $subscriber = $this->subscriber();

        $this->signedPost($this->payload('unsubscribe'), str_repeat('x', 48))->assertForbidden();

        $this->assertTrue($subscriber->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_signature_is_checked_against_the_raw_form_body(): void
    {
        $this->configureWebhook();
        $subscriber = $this->subscriber();
        $payload = $this->payload('unsubscribe');
        $rawBody = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, self::SIGNING_SECRET);

        $this->call('POST', $this->webhookUrl(), $payload, [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_X_MAILCHIMP_SIGNATURE' => 't=' . $timestamp . ',v1=' . $signature,
        ], $rawBody . '&tampered=1')->assertForbidden();

        $this->assertTrue($subscriber->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_old_or_future_signed_deliveries_are_rejected(): void
    {
        $this->configureWebhook();
        $subscriber = $this->subscriber();

        foreach ([time() - 600, time() + 600] as $timestamp) {
            $this->signedPost($this->payload('unsubscribe'), null, $timestamp)->assertForbidden();
        }

        $this->assertTrue($subscriber->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_authenticated_events_from_another_audience_cannot_change_subscribers(): void
    {
        $this->configureWebhook();
        $subscriber = $this->subscriber();
        $payload = $this->payload('unsubscribe');
        $payload['data']['list_id'] = 'otheraudience';

        $this->signedPost($payload)->assertStatus(422);

        $this->assertTrue($subscriber->fresh()->status);
        $this->assertNull($subscriber->fresh()->mailchimp_member_status);
        Http::assertNothingSent();
    }

    public function test_subscribe_event_cannot_reactivate_a_local_opt_out_or_replace_missing_consent(): void
    {
        $this->configureWebhook();
        $this->fakeRemoteMember('subscribed');
        $inactive = $this->subscriber(['status' => false]);
        $withoutConsent = $this->subscriber(['email' => 'no-consent@example.test', 'gdpr' => false]);

        $this->signedPost($this->payload('subscribe'))->assertOk();
        $payload = $this->payload('subscribe');
        $payload['data']['email'] = $withoutConsent->email;
        $this->signedPost($payload)->assertOk();

        $this->assertFalse($inactive->fresh()->status);
        $this->assertNull($inactive->fresh()->mailchimp_member_status);
        $this->assertFalse($withoutConsent->fresh()->gdpr);
        $this->assertNull($withoutConsent->fresh()->mailchimp_member_status);
        $this->assertOnlyReadOnlyRequests();
    }

    public function test_corrupt_saved_signing_secret_cannot_downgrade_post_authentication_to_token_only(): void
    {
        $this->configureWebhook();
        $subscriber = $this->subscriber();
        $this->writeStoredSettings([
            'enabled' => true,
            'webhook_token_encrypted' => Crypt::encryptString(self::TOKEN),
            'webhook_signing_secret_encrypted' => 'invalid-ciphertext',
        ]);

        $this->post($this->webhookUrl(), $this->payload('unsubscribe'))->assertForbidden();

        $this->assertTrue($subscriber->fresh()->status);
        Http::assertNothingSent();
    }

    private function configureWebhook(): void
    {
        config([
            'services.mailchimp.enabled' => true,
            'services.mailchimp.api_key' => 'test-api-key-us7',
            'services.mailchimp.server_prefix' => 'us7',
            'services.mailchimp.audience_id' => self::AUDIENCE,
            'services.mailchimp.webhook_token' => self::TOKEN,
            'services.mailchimp.webhook_signing_secret' => self::SIGNING_SECRET,
        ]);
    }

    private function subscriber(array $attributes = []): NewsletterSubscriber
    {
        return NewsletterSubscriber::query()->create(array_merge([
            'email' => 'reader+tag@example.test',
            'source' => 'homepage',
            'gdpr' => true,
            'status' => true,
            'subscribed_at' => now(),
        ], $attributes));
    }

    private function payload(string $type): array
    {
        return [
            'type' => $type,
            'data' => [
                'list_id' => self::AUDIENCE,
                'email' => 'reader+tag@example.test',
                'action' => 'unsub',
            ],
        ];
    }

    private function webhookUrl(?string $token = null): string
    {
        return route('mailchimp.webhook', ['token' => $token ?? self::TOKEN]);
    }

    private function signedPost(array $payload, ?string $token = null, ?int $timestamp = null)
    {
        $timestamp = $timestamp ?? time();
        $rawBody = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
        $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, self::SIGNING_SECRET);

        return $this->call('POST', $this->webhookUrl($token), $payload, [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_X_MAILCHIMP_SIGNATURE' => 't=' . $timestamp . ',v1=' . $signature,
        ], $rawBody);
    }

    private function fakeRemoteMember(string $status): void
    {
        Http::fake(function ($request) use ($status) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return Http::response(['id' => basename($path), 'status' => $status], 200);
        });
    }

    private function assertOnlyReadOnlyRequests(): void
    {
        foreach (Http::recorded() as $recorded) {
            $this->assertSame('GET', $recorded[0]->method());
        }
    }

    private function storedSettings(): array
    {
        return json_decode(DB::table('settings')->where('code', 'marketing')->where('key', 'mailchimp')->value('value'), true);
    }

    private function writeStoredSettings(array $payload): void
    {
        DB::table('settings')->updateOrInsert(['code' => 'marketing', 'key' => 'mailchimp'], [
            'value' => json_encode($payload, JSON_THROW_ON_ERROR),
            'json' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
