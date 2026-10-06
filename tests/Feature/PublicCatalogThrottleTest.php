<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class PublicCatalogThrottleTest extends TestCase
{
    public function test_public_catalog_routes_use_separate_named_rate_limits(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertContains(
            'throttle:catalog-search',
            $routes->getByName('pretrazi')->gatherMiddleware()
        );
        $this->assertContains(
            'throttle:catalog-suggest',
            $routes->getByName('pretrazi.suggest')->gatherMiddleware()
        );

        $filterRoute = collect($routes->getRoutes())->first(function (Route $route) {
            return $route->uri() === 'api/v2/filter/getProducts'
                && in_array('POST', $route->methods(), true);
        });

        $this->assertNotNull($filterRoute);
        $this->assertContains('throttle:catalog-filter', $filterRoute->gatherMiddleware());
    }

    /** @dataProvider catalogLimiterProvider */
    public function test_catalog_rate_limits_are_bounded_and_do_not_store_plain_ip_addresses(
        string $name,
        int $attempts
    ): void {
        $request = Request::create('/catalog-rate-limit-probe', 'GET');
        $request->server->set('REMOTE_ADDR', '203.0.113.24');

        $resolver = app(RateLimiter::class)->limiter($name);
        $this->assertNotNull($resolver);

        $limit = $resolver($request);

        $this->assertSame($attempts, $limit->maxAttempts);
        $this->assertSame(1, $limit->decayMinutes);
        $this->assertStringStartsWith($name . ':ip:', $limit->key);
        $this->assertStringNotContainsString('203.0.113.24', $limit->key);
    }

    public static function catalogLimiterProvider(): array
    {
        return [
            'filter requests' => ['catalog-filter', 90],
            'search result pages' => ['catalog-search', 30],
            'live suggestions' => ['catalog-suggest', 60],
        ];
    }
}
