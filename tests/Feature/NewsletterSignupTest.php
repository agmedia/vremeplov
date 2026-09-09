<?php

namespace Tests\Feature;

use App\Services\NewsletterSignupGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class NewsletterSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_valid_signup_is_saved_locally_and_normalized(): void
    {
        $token = app(NewsletterSignupGuard::class)->issueToken();
        $this->travel(3)->seconds();

        $this->postJson(route('newsletter.subscribe'), [
            'email' => '  Citatelj@Example.test ',
            'gdpr' => '1',
            'website' => '',
            'newsletter_started_at' => $token,
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'citatelj@example.test',
            'source' => 'homepage',
            'gdpr' => true,
            'status' => true,
        ]);
    }

    public function test_repeated_signup_does_not_create_a_duplicate(): void
    {
        foreach ([1, 2] as $attempt) {
            $token = app(NewsletterSignupGuard::class)->issueToken();
            $this->travel(3)->seconds();

            $this->postJson(route('newsletter.subscribe'), [
                'email' => 'ista@example.test',
                'gdpr' => '1',
                'website' => '',
                'newsletter_started_at' => $token,
            ])->assertOk();
        }

        $this->assertDatabaseCount('newsletter_subscribers', 1);
    }

    public function test_honeypot_submission_is_ignored_without_revealing_detection(): void
    {
        $this->postJson(route('newsletter.subscribe'), [
            'email' => 'robot@example.test',
            'gdpr' => '1',
            'website' => 'spam.example',
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_signup_requires_consent_and_a_human_submission_delay(): void
    {
        $token = app(NewsletterSignupGuard::class)->issueToken();

        $this->postJson(route('newsletter.subscribe'), [
            'email' => 'brzo@example.test',
            'gdpr' => '1',
            'website' => '',
            'newsletter_started_at' => $token,
        ])->assertStatus(422)->assertJsonValidationErrors('newsletter_started_at');

        $token = app(NewsletterSignupGuard::class)->issueToken();
        $this->travel(3)->seconds();

        $this->postJson(route('newsletter.subscribe'), [
            'email' => 'bez-privole@example.test',
            'website' => '',
            'newsletter_started_at' => $token,
        ])->assertStatus(422)->assertJsonValidationErrors('gdpr');
    }
}
