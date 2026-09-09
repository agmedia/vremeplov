<?php

namespace Tests\Feature;

use App\Models\Back\Marketing\NewsletterSubscriber;
use App\Models\User;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNewsletterSubscriberTest extends TestCase
{
    use RefreshDatabase;

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

    private function subscriber(string $email, bool $active, string $source): NewsletterSubscriber
    {
        return NewsletterSubscriber::query()->create([
            'email' => $email,
            'source' => $source,
            'gdpr' => true,
            'status' => $active,
            'subscribed_at' => now(),
        ]);
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
