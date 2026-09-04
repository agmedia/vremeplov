<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminApiRouteSecurityTest extends TestCase
{
    public function test_every_administration_api_route_requires_a_web_login(): void
    {
        foreach ($this->administrationRoutes() as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, $routeName . ' route is missing.');
            $this->assertContains('auth:web', $route->gatherMiddleware(), $routeName);
            $this->assertContains('verified', $route->gatherMiddleware(), $routeName);
            $this->assertContains('no.customers', $route->gatherMiddleware(), $routeName);
        }
    }

    public function test_sensitive_store_configuration_routes_require_an_administrator(): void
    {
        foreach ($this->administratorOnlyRoutes() as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, $routeName . ' route is missing.');
            $this->assertContains('admin.manager', $route->gatherMiddleware(), $routeName);
        }
    }

    public function test_unauthenticated_requests_cannot_reach_administration_api_controllers(): void
    {
        $this->getJson(route('products.autocomplete'))->assertUnauthorized();
        $this->postJson(route('products.destroy.api'))->assertUnauthorized();
        $this->postJson(route('api.payment.store'))->assertUnauthorized();
        $this->postJson(route('api.order.status.change'))->assertUnauthorized();
        $this->postJson(route('api.order.send.gls'))->assertUnauthorized();
    }

    public function test_unreviewed_keks_endpoints_are_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('services.keks.enabled'));
        $this->assertNull(Route::getRoutes()->getByName('checkout.success.keks'));
        $this->assertNull(Route::getRoutes()->getByName('keks.provjera'));
    }

    private function administrationRoutes(): array
    {
        return array_merge([
            'products.autocomplete',
            'products.destroy.image',
            'products.change.status',
            'products.update.item',
            'api.categories.groups.store',
            'api.categories.groups.destroy',
            'actions.destroy.api',
            'reviews.destroy.api',
            'authors.destroy.api',
            'publishers.destroy.api',
            'products.destroy.api',
            'blogs.destroy.api',
            'blogs.upload.image',
            'api.order.send.boxnow',
            'api.order.tracking.boxnow.refresh',
        ], $this->administratorOnlyRoutes());
    }

    private function administratorOnlyRoutes(): array
    {
        return [
            'widget.destroy',
            'widget.api.get-links',
            'api.api.import',
            'api.api.upload',
            'api.application.basic.store',
            'api.application.google-api.store.key',
            'api.order.status.store',
            'api.order.status.destroy',
            'api.order.status.change',
            'api.order.send.gls',
            'api.payment.store',
            'api.payment.destroy',
            'api.shipping.store',
            'api.shipping.destroy',
            'api.taxes.store',
            'api.taxes.destroy',
            'api.currencies.store',
            'api.currencies.store.main',
            'api.currencies.destroy',
        ];
    }
}
