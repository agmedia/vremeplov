<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleLoginSettingsService;
use App\Services\GoogleOidcService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Throwable;

class GoogleLoginController extends Controller
{
    private const TRANSACTION_TTL = 600;

    public function redirect(
        Request $request,
        GoogleLoginSettingsService $settings,
        GoogleOidcService $oidc
    ): RedirectResponse {
        if (Auth::check()) {
            return $this->redirectAuthenticated($request, Auth::user());
        }

        if (! $settings->enabled()) {
            return $this->fail($request, 'Google prijava trenutačno nije dostupna. Pokušajte ponovno kasnije.');
        }

        try {
            $state = $this->randomToken(32);
            $nonce = $this->randomToken(32);
            $codeVerifier = $this->randomToken(64);
            $codeChallenge = $this->base64UrlEncode(hash('sha256', $codeVerifier, true));
        } catch (Throwable $exception) {
            return $this->fail(
                $request,
                'Google prijava trenutačno nije dostupna. Pokušajte ponovno kasnije.',
                'Unable to create OAuth security tokens: '.$exception->getMessage()
            );
        }

        $request->session()->put('google_login_transaction', [
            'state' => $state,
            'nonce' => $nonce,
            'code_verifier' => $codeVerifier,
            'redirect' => $this->safeRedirect($request, $request->query('redirect')),
            'created_at' => time(),
        ]);

        $credentials = $settings->get();

        return redirect()->away($oidc->authorizationUrl(
            $credentials['client_id'],
            route('google.login.callback'),
            $state,
            $nonce,
            $codeChallenge
        ));
    }

    public function callback(
        Request $request,
        GoogleLoginSettingsService $settings,
        GoogleOidcService $oidc
    ): RedirectResponse {
        if (Auth::check()) {
            $request->session()->forget('google_login_transaction');

            return $this->redirectAuthenticated($request, Auth::user());
        }

        if (! $settings->enabled()) {
            $request->session()->forget('google_login_transaction');

            return $this->fail($request, 'Google prijava trenutačno nije dostupna. Pokušajte ponovno kasnije.');
        }

        $transaction = (array) $request->session()->pull('google_login_transaction', []);

        if (! $this->validTransaction($transaction)) {
            return $this->fail($request, 'Zahtjev za Google prijavu je istekao. Pokušajte ponovno.');
        }

        $state = (string) $request->query('state', '');

        if ($state === '' || ! hash_equals($transaction['state'], $state)) {
            return $this->fail(
                $request,
                'Google prijava nije mogla biti potvrđena. Pokušajte ponovno.',
                'OAuth state validation failed.',
                $transaction['redirect'] ?? null
            );
        }

        $googleError = (string) $request->query('error', '');

        if ($googleError !== '') {
            if ($googleError === 'access_denied') {
                return $this->fail(
                    $request,
                    'Google prijava je otkazana.',
                    '',
                    $transaction['redirect'] ?? null
                );
            }

            return $this->fail(
                $request,
                'Google prijava trenutačno nije dostupna. Pokušajte ponovno kasnije.',
                'Google returned OAuth error: '.preg_replace('/[^a-z0-9_-]/i', '', $googleError),
                $transaction['redirect'] ?? null
            );
        }

        $code = (string) $request->query('code', '');

        if ($code === '' || strlen($code) > 4096) {
            return $this->fail(
                $request,
                'Google prijava nije mogla biti potvrđena. Pokušajte ponovno.',
                'Authorization code is missing or invalid.',
                $transaction['redirect'] ?? null
            );
        }

        try {
            $credentials = $settings->get();
            $token = $oidc->exchangeAuthorizationCode(
                $credentials['client_id'],
                $credentials['client_secret'],
                route('google.login.callback'),
                $code,
                $transaction['code_verifier']
            );
            $claims = $oidc->verifyIdToken(
                $token['id_token'],
                $credentials['client_id'],
                $transaction['nonce']
            );
        } catch (Throwable $exception) {
            return $this->fail(
                $request,
                'Google prijava trenutačno nije dostupna. Pokušajte ponovno kasnije.',
                $exception->getMessage(),
                $transaction['redirect'] ?? null
            );
        }

        $email = strtolower(trim((string) ($claims['email'] ?? '')));

        if (! $this->authoritativeGoogleEmail($email, $claims)) {
            return $this->fail(
                $request,
                'Za izravnu prijavu koristite verificiranu Gmail ili Google Workspace adresu.',
                '',
                $transaction['redirect'] ?? null
            );
        }

        $user = User::query()->with('details')->where('email', $email)->first();

        if (! $user || ! $user->details) {
            return $this->fail(
                $request,
                'Za ovu Google e-mail adresu ne postoji korisnički račun. Prijavite se zaporkom ili izradite račun.',
                '',
                $transaction['redirect'] ?? null
            );
        }

        if (! $user->details->status) {
            return $this->fail(
                $request,
                'Ovaj korisnički račun nije aktivan. Obratite se korisničkoj podršci.',
                '',
                $transaction['redirect'] ?? null
            );
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => true,
                'url.intended' => $this->authenticatedRedirectUrl(
                    $request,
                    $user,
                    $transaction['redirect'] ?? null
                ),
            ]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return $this->redirectAuthenticated($request, $user, $transaction['redirect'] ?? null);
    }

    private function redirectAuthenticated(Request $request, User $user, ?string $redirect = null): RedirectResponse
    {
        return redirect()->to($this->authenticatedRedirectUrl($request, $user, $redirect));
    }

    private function authenticatedRedirectUrl(Request $request, User $user, ?string $redirect = null): string
    {
        if ($user->isAdministrator()) {
            $request->session()->forget('url.intended');

            return route('dashboard');
        }

        return $this->safeRedirect($request, $redirect) ?: route('moj-racun');
    }

    private function authoritativeGoogleEmail(string $email, array $claims): bool
    {
        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            return true;
        }

        return ! empty($claims['hd']) && is_string($claims['hd']);
    }

    private function validTransaction(array $transaction): bool
    {
        return isset(
            $transaction['state'],
            $transaction['nonce'],
            $transaction['code_verifier'],
            $transaction['created_at']
        )
            && is_string($transaction['state'])
            && is_string($transaction['nonce'])
            && is_string($transaction['code_verifier'])
            && is_numeric($transaction['created_at'])
            && (int) $transaction['created_at'] >= (time() - self::TRANSACTION_TTL)
            && (int) $transaction['created_at'] <= (time() + 60);
    }

    private function safeRedirect(Request $request, $candidate): ?string
    {
        if (! is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        $candidate = html_entity_decode(trim($candidate), ENT_QUOTES, 'UTF-8');

        if (str_starts_with($candidate, '/') && ! str_starts_with($candidate, '//')) {
            $candidate = $request->getSchemeAndHttpHost().$candidate;
        }

        $target = parse_url($candidate);

        if (! $target || empty($target['scheme']) || empty($target['host'])
            || ! in_array(strtolower($target['scheme']), ['http', 'https'], true)) {
            return null;
        }

        if (strtolower($target['host']) !== strtolower($request->getHost())) {
            return null;
        }

        $targetPort = $target['port'] ?? null;
        $requestPort = $request->getPort();
        $defaultPort = strtolower($target['scheme']) === 'https' ? 443 : 80;

        if (($targetPort ?? $defaultPort) !== $requestPort) {
            return null;
        }

        $blockedPaths = [
            route('logout', [], false),
            route('google.login.redirect', [], false),
            route('google.login.callback', [], false),
        ];

        if (in_array($target['path'] ?? '/', $blockedPaths, true)) {
            return null;
        }

        return $candidate;
    }

    private function randomToken(int $bytes): string
    {
        return $this->base64UrlEncode(random_bytes($bytes));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function fail(
        Request $request,
        string $message,
        string $logMessage = '',
        ?string $redirect = null
    ): RedirectResponse {
        if ($logMessage !== '') {
            Log::warning('Google Login: '.$logMessage);
        }

        $target = $this->safeRedirect($request, $redirect) ?: route('index');

        return redirect()->to($target)->with('auth_error', $message);
    }
}
