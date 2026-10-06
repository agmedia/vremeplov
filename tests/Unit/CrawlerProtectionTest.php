<?php

namespace Tests\Unit;

use Tests\TestCase;

class CrawlerProtectionTest extends TestCase
{
    private const BLOCKED_CRAWLERS = [
        'GPTBot',
        'ChatGPT-User',
        'ClaudeBot',
        'Anthropic-ai',
        'AionBot',
        'meta-externalagent',
        'meta-externalfetcher',
        'ReflectionBot',
        'PerplexityBot',
        'Google-Extended',
        'Bytespider',
        'Cohere-ai',
        'CCBot',
        'SemrushBot',
        'Baiduspider',
        'DataForSeoBot',
        'GeedoShopProductFinder',
        'SERankingBacklinksBot',
    ];

    public function test_observed_crawlers_are_blocked_before_the_front_controller(): void
    {
        $apache = file_get_contents(public_path('.htaccess'));
        $condition = collect(preg_split('/\R/', $apache))
            ->first(fn (string $line) => str_contains($line, 'HTTP_USER_AGENT'));

        $this->assertIsString($condition);

        foreach (self::BLOCKED_CRAWLERS as $crawler) {
            $this->assertStringContainsString($crawler, $condition);
        }

        $this->assertStringContainsString('RewriteRule ^ - [F,L]', $apache);
        $this->assertStringNotContainsString('Googlebot', $condition);
        $this->assertStringNotContainsString('Bingbot', $condition);
    }

    public function test_robots_policy_matches_the_pre_php_blocks_and_hides_login_routes(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        foreach (self::BLOCKED_CRAWLERS as $crawler) {
            $this->assertStringContainsString("User-agent: {$crawler}\nDisallow: /", $robots);
        }

        $this->assertStringContainsString("User-agent: *", $robots);
        $this->assertStringContainsString("Disallow: /prijava/", $robots);
    }

    public function test_google_login_requires_a_real_post_and_crawler_gets_are_blocked_before_php(): void
    {
        $apache = file_get_contents(public_path('.htaccess'));
        $view = file_get_contents(resource_path('views/front/layouts/modals/login.blade.php'));

        $this->assertStringContainsString('class="google-login-button" type="submit"', $view);
        $this->assertStringContainsString('id="google-login-form" method="POST"', $view);
        $this->assertStringContainsString('form="google-login-form"', $view);
        $this->assertStringContainsString('RewriteRule ^prijava/google/?$ - [F,L]', $apache);
    }
}
