<?php

namespace Tests\Feature;

use App\Models\Back\Marketing\NewsletterSubscriber;
use App\Services\Mailchimp\MailchimpApi;
use App\Services\Mailchimp\MailchimpConnectionSettings;
use App\Services\Mailchimp\MailchimpWebhookService;
use App\Services\Mailchimp\NewsletterSyncService;
use App\Services\NewsletterSignupGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class MailchimpNewsletterSyncTest extends TestCase
{
    use RefreshDatabase;

    private $connection;
    private $remote = [];
    private $archived = [];
    private $doubleOptIn = true;
    private $created = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = [
            'enabled' => true, 'api_key' => 'test-api-key-us1', 'server_prefix' => 'us1',
            'audience_id' => 'audience123',
            'webhook_token' => str_repeat('a', 64), 'webhook_signing_secret' => '',
        ];
        $settings = Mockery::mock(MailchimpConnectionSettings::class);
        $settings->shouldReceive('connection')->andReturnUsing(function () { return $this->connection; });
        $this->app->instance(MailchimpConnectionSettings::class, $settings);
        $this->fakeApi();
    }

    public function test_explicit_new_signup_uses_confirmation_and_is_idempotent(): void
    {
        $subscriber = $this->subscriber();
        $service = app(NewsletterSyncService::class);
        $this->assertSame('pending_confirmation', $service->sync($subscriber->id)['status']);
        $this->assertSame('pending_confirmation', $service->sync($subscriber->id)['status']);
        $this->assertSame(1, $this->created);
        Http::assertSent(function ($request) {
            return $request->method() === 'POST' && $request['status'] === 'pending'
                && $request['email_address'] === 'reader@example.test';
        });
        $this->assertDatabaseHas('newsletter_subscribers', [
            'id' => $subscriber->id, 'mailchimp_sync_status' => 'pending_confirmation',
            'mailchimp_member_status' => 'pending', 'status' => true,
        ]);
        Http::assertNotSent(function ($request) { return in_array($request->method(), ['PATCH', 'PUT', 'DELETE']); });
    }

    public function test_public_signup_stays_durably_pending_until_scheduled_command_runs(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $token = app(NewsletterSignupGuard::class)->issueToken();
        $this->travel(3)->seconds();
        $this->postJson(route('newsletter.subscribe'), [
            'email' => 'reader@example.test', 'gdpr' => '1', 'website' => '',
            'newsletter_started_at' => $token,
        ])->assertOk();
        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'reader@example.test', 'mailchimp_sync_status' => 'pending',
            'mailchimp_sync_attempts' => 0,
        ]);
        Http::assertNothingSent();
        $this->artisan('newsletter:sync-mailchimp', ['--limit' => 25])->assertExitCode(0);
        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'reader@example.test', 'mailchimp_sync_status' => 'pending_confirmation',
            'mailchimp_member_status' => 'pending',
        ]);
        $this->assertSame(1, $this->created);
    }

    public function test_single_opt_in_audience_creates_subscribed_contact(): void
    {
        $this->doubleOptIn = false;
        $result = app(NewsletterSyncService::class)->sync($this->subscriber()->id);
        $this->assertSame('synced', $result['status']);
        Http::assertSent(function ($request) { return $request->method() === 'POST' && $request['status'] === 'subscribed'; });
    }

    public function test_missing_opt_in_setting_defaults_to_confirmation(): void
    {
        $this->doubleOptIn = null;
        $this->assertSame('pending_confirmation', app(NewsletterSyncService::class)->sync($this->subscriber()->id)['status']);
    }

    /** @dataProvider remoteStatuses */
    public function test_existing_remote_status_is_never_overwritten(string $remoteStatus, string $expected): void
    {
        $subscriber = $this->subscriber();
        $this->remote[md5($subscriber->email)] = $remoteStatus;
        $result = app(NewsletterSyncService::class)->sync($subscriber->id);
        $this->assertSame($expected, $result['status']);
        $this->assertSame($remoteStatus, $result['remote_status']);
        Http::assertSentCount(1);
        Http::assertNotSent(function ($request) { return $request->method() !== 'GET'; });
        if (in_array($remoteStatus, ['unsubscribed', 'cleaned', 'archived'])) {
            $this->assertFalse($subscriber->fresh()->status);
        }
    }

    public function remoteStatuses(): array
    {
        return [
            ['subscribed', 'synced'], ['unsubscribed', 'preserved'], ['cleaned', 'preserved'],
            ['pending', 'pending_confirmation'], ['transactional', 'preserved'], ['archived', 'preserved'],
        ];
    }

    public function test_local_opt_out_or_missing_consent_never_reaches_mailchimp(): void
    {
        foreach ([['status' => false], ['gdpr' => false]] as $index => $attributes) {
            $subscriber = $this->subscriber(array_merge(['email' => 'reader' . $index . '@example.test'], $attributes));
            $this->assertSame('skipped', app(NewsletterSyncService::class)->sync($subscriber->id)['status']);
        }
        Http::assertNothingSent();
    }

    public function test_imported_record_without_explicit_signup_is_not_created(): void
    {
        $result = app(NewsletterSyncService::class)->sync($this->subscriber(['source' => 'import'])->id);
        $this->assertSame('explicit_signup_required', $result['error_code']);
        $this->assertSame(0, $this->created);
    }

    public function test_archived_address_hidden_by_member_404_is_preserved(): void
    {
        $subscriber = $this->subscriber();
        $this->archived = [['id' => md5($subscriber->email)]];
        $this->assertSame('preserved', app(NewsletterSyncService::class)->sync($subscriber->id)['status']);
        $this->assertFalse($subscriber->fresh()->status);
        $this->assertSame(0, $this->created);
    }

    public function test_archive_scan_checks_later_pages(): void
    {
        $hash = md5('last@example.test');
        Http::swap(new Factory());
        Http::fake(function ($request) use ($hash) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $rows = (int) $query['offset'] === 0
                ? array_map(function ($id) { return ['id' => md5('archive-' . $id)]; }, range(1, 1000))
                : [['id' => $hash]];
            return Http::response(['members' => $rows, 'total_items' => 1001]);
        });
        $this->assertTrue(app(MailchimpApi::class)->isArchived($this->connection, $hash));
        Http::assertSentCount(2);
    }

    public function test_previously_synced_contact_missing_remotely_is_never_recreated(): void
    {
        $subscriber = $this->subscriber(['mailchimp_synced_at' => now(), 'mailchimp_member_status' => 'subscribed']);
        $result = app(NewsletterSyncService::class)->sync($subscriber->id);
        $this->assertSame('remote_member_removed', $result['error_code']);
        $this->assertSame(0, $this->created);
        $this->assertFalse($subscriber->fresh()->status);
    }

    public function test_new_opt_ins_are_not_limited_by_local_contact_count(): void
    {
        $rows = [];
        for ($index = 0; $index < 501; $index++) {
            $rows[] = ['email' => 'existing' . $index . '@example.test', 'source' => 'homepage',
                'gdpr' => true, 'status' => true, 'subscribed_at' => now()];
        }
        NewsletterSubscriber::query()->insert($rows);
        $this->assertSame('pending_confirmation', app(NewsletterSyncService::class)->sync($this->subscriber()->id)['status']);
        $this->assertSame(1, $this->created);
    }

    public function test_incomplete_archive_page_fails_closed(): void
    {
        Http::swap(new Factory());
        Http::fake(function ($request) {
            return strpos($request->url(), 'status=archived') !== false
                ? Http::response(['members' => [], 'total_items' => 1001]) : Http::response([], 404);
        });
        $result = app(NewsletterSyncService::class)->sync($this->subscriber()->id);
        $this->assertSame('retry', $result['status']);
        $this->assertSame('archive_check_incomplete', $result['error_code']);
        Http::assertNotSent(function ($request) { return $request->method() !== 'GET'; });
    }

    public function test_api_failure_is_retryable_without_persisting_upstream_details(): void
    {
        Http::swap(new Factory());
        Http::fake(['*' => Http::response(['detail' => 'reader@example.test secret-api-key'], 429)]);
        $subscriber = $this->subscriber();
        $result = app(NewsletterSyncService::class)->sync($subscriber->id);
        $this->assertSame('retry', $result['status']);
        $this->assertSame('remote_http_429', $subscriber->fresh()->mailchimp_last_error);
        $this->assertStringNotContainsString('secret', $subscriber->fresh()->mailchimp_last_error);
        $this->assertNotNull($subscriber->fresh()->mailchimp_next_attempt_at);
    }

    public function test_contact_lock_prevents_duplicate_work(): void
    {
        $subscriber = $this->subscriber();
        $service = app(NewsletterSyncService::class);
        $lock = $service->subscriberLock($subscriber->id)->acquire(0);
        try {
            $this->assertSame('busy', $service->sync($subscriber->id)['status']);
            Http::assertNothingSent();
        } finally { $lock->release(); }
    }

    public function test_due_retry_runner_skips_backoff_and_caps_batch(): void
    {
        $later = $this->subscriber(['mailchimp_sync_status' => 'retry', 'mailchimp_next_attempt_at' => now()->addHour()]);
        $due = $this->subscriber(['email' => 'due@example.test']);
        $this->remote[md5($due->email)] = 'subscribed';
        $counts = app(NewsletterSyncService::class)->retryPending(1);
        $this->assertSame(['processed' => 1, 'synced' => 1], $counts);
        $this->assertSame('retry', $later->fresh()->mailchimp_sync_status);
    }

    public function test_subscribe_webhook_without_prior_sync_is_rechecked_by_retry_runner(): void
    {
        $subscriber = $this->subscriber();
        $this->assertTrue(app(MailchimpWebhookService::class)->handle([
            'type' => 'subscribe',
            'data' => ['list_id' => 'audience123', 'email' => $subscriber->email],
        ]));
        $this->assertSame('synced', $subscriber->fresh()->mailchimp_sync_status);
        $this->assertNull($subscriber->fresh()->mailchimp_last_attempt_at);
        $this->remote[md5($subscriber->email)] = 'unsubscribed';
        $counts = app(NewsletterSyncService::class)->retryPending();
        $this->assertSame(['processed' => 1, 'preserved' => 1], $counts);
        $this->assertFalse($subscriber->fresh()->status);
        $this->assertSame('unsubscribed', $subscriber->fresh()->mailchimp_member_status);
        Http::assertSentCount(1);
        $this->assertSame(0, $this->created);
    }

    public function test_connection_summary_contains_no_secrets_or_contact_limit(): void
    {
        $summary = app(NewsletterSyncService::class)->connectionStatus();
        $this->assertArrayNotHasKey('contact_limit', $summary);
        $this->assertTrue($summary['ready']);
        $this->assertArrayNotHasKey('api_key', $summary);
        $this->assertArrayNotHasKey('webhook_token', $summary);
        Http::assertNothingSent();
    }

    public function test_webhook_verifies_raw_hmac_and_rejects_tampering_expiry_and_token_downgrade(): void
    {
        $this->connection['webhook_signing_secret'] = 'signing-test-secret';
        $service = app(MailchimpWebhookService::class);
        $body = 'type=unsubscribe&data%5Bemail%5D=reader%40example.test';
        $timestamp = time();
        $signature = 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $body, 'signing-test-secret');
        $url = '/mailchimp/webhook?token=' . str_repeat('a', 64);
        $request = Request::create($url, 'POST', [], [], [], ['HTTP_X_MAILCHIMP_SIGNATURE' => $signature], $body);
        $this->assertTrue($service->authorized($request));
        $tampered = Request::create($url, 'POST', [], [], [], ['HTTP_X_MAILCHIMP_SIGNATURE' => $signature], $body . 'x');
        $this->assertFalse($service->authorized($tampered));
        $expiredAt = time() - 301;
        $expired = 't=' . $expiredAt . ',v1=' . hash_hmac('sha256', $expiredAt . '.' . $body, 'signing-test-secret');
        $request->headers->set('X-Mailchimp-Signature', $expired);
        $this->assertFalse($service->authorized($request));
        $this->assertFalse($service->authorized(Request::create('/mailchimp/webhook?token=' . str_repeat('a', 64), 'POST')));
    }

    public function test_legacy_webhook_token_is_constant_time_checked_and_opt_out_is_idempotent(): void
    {
        $subscriber = $this->subscriber();
        $service = app(MailchimpWebhookService::class);
        $this->assertTrue($service->authorized(Request::create('/mailchimp/webhook?token=' . str_repeat('a', 64), 'POST')));
        $this->assertFalse($service->authorized(Request::create('/mailchimp/webhook?token=bad', 'POST')));
        $payload = ['type' => 'unsubscribe', 'data' => ['list_id' => 'audience123', 'email' => $subscriber->email]];
        $this->assertTrue($service->handle($payload));
        $this->assertTrue($service->handle($payload));
        $this->assertFalse($subscriber->fresh()->status);
        $this->assertSame('unsubscribed', $subscriber->fresh()->mailchimp_member_status);
        Http::assertNothingSent();
    }

    public function test_webhook_never_reactivates_local_opt_out_or_handles_another_audience(): void
    {
        $subscriber = $this->subscriber(['status' => false]);
        $service = app(MailchimpWebhookService::class);
        $this->assertTrue($service->handle(['type' => 'subscribe', 'data' => ['list_id' => 'audience123', 'email' => $subscriber->email]]));
        $this->assertFalse($subscriber->fresh()->status);
        $this->assertFalse($service->handle(['type' => 'cleaned', 'data' => ['list_id' => 'other', 'email' => $subscriber->email]]));
    }

    public function test_email_change_webhook_suppresses_old_email_without_overwriting_new_contact(): void
    {
        $old = $this->subscriber();
        $new = $this->subscriber(['email' => 'new@example.test', 'status' => false]);
        $payload = ['type' => 'upemail', 'data' => ['list_id' => 'audience123',
            'old_email' => $old->email, 'new_email' => $new->email]];
        $this->assertTrue(app(MailchimpWebhookService::class)->handle($payload));
        $this->assertFalse($old->fresh()->status);
        $this->assertSame('remote_email_changed', $old->fresh()->mailchimp_last_error);
        $this->assertSame('reader@example.test', $old->fresh()->email);
        $this->assertFalse($new->fresh()->status);
        $this->assertSame('new@example.test', $new->fresh()->email);
        Http::assertNothingSent();
    }

    private function subscriber(array $attributes = []): NewsletterSubscriber
    {
        return NewsletterSubscriber::query()->create(array_merge([
            'email' => 'reader@example.test', 'source' => 'homepage', 'gdpr' => true,
            'status' => true, 'subscribed_at' => now(),
        ], $attributes));
    }

    private function fakeApi(): void
    {
        Http::fake(function ($request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            if ($request->method() === 'POST') {
                $hash = md5($request['email_address']);
                $status = $request['status'];
                $this->remote[$hash] = $status;
                $this->created++;
                return Http::response(['id' => $hash, 'status' => $status]);
            }
            if (preg_match('#/members/([a-f0-9]{32})$#', $path, $match)) {
                return isset($this->remote[$match[1]])
                    ? Http::response(['id' => $match[1], 'status' => $this->remote[$match[1]]]) : Http::response([], 404);
            }
            if ($path === '/3.0/lists/audience123' && ! isset($query['status'])) {
                return Http::response(['id' => 'audience123', 'double_optin' => $this->doubleOptIn]);
            }
            if (($query['status'] ?? '') === 'archived') {
                return Http::response(['members' => $this->archived, 'total_items' => count($this->archived)]);
            }
            return Http::response([], 500);
        });
    }
}
