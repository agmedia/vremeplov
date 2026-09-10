<?php

namespace Tests\Feature;

use Tests\TestCase;

class ImageCacheFallbackTest extends TestCase
{
    /** @test */
    public function image_cache_returns_a_placeholder_for_an_unreadable_source(): void
    {
        $response = $this->get('/cache/image?src=' . urlencode('/definitely/missing/book-cover.jpg'));

        $response->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function thumbnail_cache_uses_safe_defaults_and_a_placeholder(): void
    {
        $response = $this->get('/cache/thumb?src=' . urlencode('/definitely/missing/book-cover.jpg'));

        $response->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
    }
}
