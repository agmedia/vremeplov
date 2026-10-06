<?php

namespace Tests\Feature;

use App\Support\LocalImageSourceResolver;
use Tests\TestCase;

class ImageCacheFallbackTest extends TestCase
{
    /** @test */
    public function image_cache_returns_a_placeholder_for_an_unreadable_source(): void
    {
        $response = $this->get('/cache/image?src=' . urlencode('/definitely/missing/book-cover.jpg'));

        $response->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=', (string) $response->headers->get('Cache-Control'));
        $this->assertNull($response->headers->get('Set-Cookie'));
    }

    /** @test */
    public function thumbnail_cache_uses_safe_defaults_and_a_placeholder(): void
    {
        $response = $this->get('/cache/thumb?src=' . urlencode('/definitely/missing/book-cover.jpg'));

        $response->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertNull($response->headers->get('Set-Cookie'));
    }

    /** @test */
    public function image_cache_accepts_valid_local_images_and_same_origin_urls(): void
    {
        config(['app.url' => 'https://www.antikvarijat-vremeplov.hr']);
        $resolver = app(LocalImageSourceResolver::class);
        $expected = realpath(public_path('media/img/thumb-product.jpg'));

        $this->assertSame($expected, $resolver->resolve('/media/img/thumb-product.jpg'));
        $this->assertSame(
            $expected,
            $resolver->resolve('https://www.antikvarijat-vremeplov.hr/media/img/thumb-product.jpg?v=1')
        );

        $this->get('/cache/image?src=' . urlencode('/media/img/thumb-product.jpg'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    /** @test */
    public function image_cache_rejects_remote_wrappers_private_hosts_and_traversal(): void
    {
        $resolver = app(LocalImageSourceResolver::class);

        foreach ([
            'https://example.test/cover.jpg',
            'http://127.0.0.1/server-status',
            'http://169.254.169.254/latest/meta-data/',
            'file:///etc/passwd',
            'data:image/png;base64,AAAA',
            '/../composer.json',
        ] as $source) {
            $this->assertNull($resolver->resolve($source), $source . ' must not resolve');
        }

        $this->get('/cache/image?src=' . urlencode('http://169.254.169.254/latest/meta-data/'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    /** @test */
    public function image_cache_checks_source_limits_before_decoding(): void
    {
        config(['imagecache.max_source_pixels' => 100]);

        $this->assertNull(
            app(LocalImageSourceResolver::class)->resolve('/media/img/thumb-product.jpg')
        );
    }

    /** @test */
    public function thumbnail_size_is_limited_to_a_small_configured_set(): void
    {
        $source = urlencode('/media/img/thumb-product.jpg');

        $allowed = $this->get('/cache/thumb?src='.$source.'&size=100x100');
        $allowed->assertOk();
        [$allowedWidth, $allowedHeight] = getimagesizefromstring($allowed->getContent());
        $this->assertSame([100, 100], [$allowedWidth, $allowedHeight]);

        $unbounded = $this->get('/cache/thumb?src='.$source.'&size=1199x1200');
        $unbounded->assertOk();
        [$fallbackWidth, $fallbackHeight] = getimagesizefromstring($unbounded->getContent());
        $this->assertSame([400, 400], [$fallbackWidth, $fallbackHeight]);
    }

    /** @test */
    public function image_cache_endpoints_share_a_per_ip_rate_limit(): void
    {
        $client = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77']);
        $source = urlencode('/definitely/missing/book-cover.jpg');

        for ($request = 0; $request < 30; $request++) {
            $client->get('/cache/image?src=' . $source)->assertOk();
        }

        $client->get('/cache/thumb?src=' . $source)->assertStatus(429);
    }
}
