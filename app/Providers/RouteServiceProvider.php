<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/admin/dashboard';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    /// protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->removeIndexPHPFromURL();
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }


    /**
     * Write code on Method
     *
     * @return response()
     */
    protected function removeIndexPHPFromURL()
    {
        if (config('app.env') == 'production') {
            if ( ! Str::contains(request()->fullUrl(), 'https://www.')) {
                $url = str_replace('https://', 'https://www.', request()->fullUrl());

                if (strlen($url) > 0) {
                    header("Location: $url", true, 301);
                    exit;
                }
            }
        }

        if (Str::contains(request()->getRequestUri(), '/index.php/')) {
            $url = str_replace('index.php/', '', request()->getRequestUri());

            if (strlen($url) > 0) {
                header("Location: $url", true, 301);
                exit;
            }
        }
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60);
        });

        RateLimiter::for('newsletter', function (Request $request) {
            $secret = (string) config('app.key');
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower(trim($email)) : '';
            $ip = (string) $request->ip();

            $limits = [
                Limit::perMinutes(10, 6)
                    ->by('newsletter:ip:' . hash_hmac('sha256', $ip, $secret)),
            ];

            if ($email !== '') {
                $limits[] = Limit::perDay(3)
                    ->by('newsletter:email-ip:' . hash_hmac('sha256', $email . '|' . $ip, $secret));
            }

            return $limits;
        });
    }
}
