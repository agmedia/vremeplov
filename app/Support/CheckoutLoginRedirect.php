<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class CheckoutLoginRedirect
{
    private const SESSION_KEY = 'auth.checkout_redirect';

    public static function capture(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);

        $redirect = self::validate((string) $request->input('_redirect_to', ''));

        if ($redirect) {
            $request->session()->put(self::SESSION_KEY, $redirect);
        }
    }

    public static function pull(Request $request): ?string
    {
        return self::validate((string) $request->session()->pull(self::SESSION_KEY, ''));
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    private static function validate(string $candidate): ?string
    {
        $candidate = html_entity_decode(trim($candidate), ENT_QUOTES, 'UTF-8');

        if ($candidate === '' || ! str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return null;
        }

        if (! self::isCheckoutUrl($candidate)) {
            return null;
        }

        return $candidate;
    }

    private static function isCheckoutUrl(string $candidate): bool
    {
        $path = parse_url($candidate, PHP_URL_PATH);

        return is_string($path) && in_array($path, self::checkoutPaths(), true);
    }

    private static function checkoutPaths(): array
    {
        $routeNames = [
            'kosarica',
            'naplata',
            'pregled',
            'checkout',
            'checkout.success',
            'checkout.error',
        ];

        return array_values(array_unique(array_map(
            fn (string $routeName) => route($routeName, [], false),
            array_filter($routeNames, fn (string $routeName) => Route::has($routeName))
        )));
    }
}
