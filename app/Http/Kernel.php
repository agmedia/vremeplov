<?php

namespace App\Http;

use App\Support\DatabaseCapacityLeaseManager;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Throwable;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Fruitcake\Cors\HandleCors::class,
        \App\Http\Middleware\LimitConcurrentDatabaseRequests::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \App\Http\Middleware\CaptureCheckoutLoginRedirect::class,
            \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'no.customers' => \App\Http\Middleware\RedirectCustomer::class,
        'admin.manager' => \App\Http\Middleware\RequireAdministrator::class,
        'boxnow.manager' => \App\Http\Middleware\RequireBoxNowManager::class,
        'review.backfill.admin' => \App\Http\Middleware\RequireProductReviewBackfillAdmin::class,
    ];

    /**
     * Keep the capacity lease through response sending and every terminating
     * callback, then close created PDO connections before releasing the slot.
     */
    public function terminate($request, $response)
    {
        try {
            parent::terminate($request, $response);
        } finally {
            $leases = $this->app->make(DatabaseCapacityLeaseManager::class);

            if ($leases->hasLease($request)) {
                $this->disconnectResolvedDatabaseConnections();
                $leases->release($request);
            }
        }
    }

    private function disconnectResolvedDatabaseConnections(): void
    {
        if (! $this->app->resolved('db')) {
            return;
        }

        try {
            $connections = $this->app->make('db')->getConnections();
        } catch (Throwable $exception) {
            return;
        }

        foreach ($connections as $connection) {
            try {
                $connection->disconnect();
            } catch (Throwable $exception) {
                // Releasing the OS-level lock remains mandatory at shutdown.
            }
        }
    }
}
