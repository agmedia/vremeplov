<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleOidcService
{
    private const AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const JWKS_ENDPOINT = 'https://www.googleapis.com/oauth2/v3/certs';
    private const ISSUER = 'https://accounts.google.com';
    private const JWKS_CACHE_KEY = 'google.oidc.jwks';

    public function authorizationUrl(
        string $clientId,
        string $redirectUri,
        string $state,
        string $nonce,
        string $codeChallenge
    ): string {
        return self::AUTHORIZATION_ENDPOINT.'?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        string $code,
        string $codeVerifier
    ): array {
        $response = Http::asForm()
            ->acceptJson()
            ->withOptions(['connect_timeout' => 5])
            ->timeout(12)
            ->post(self::TOKEN_ENDPOINT, [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
                'code_verifier' => $codeVerifier,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google authentication token request failed.');
        }

        $payload = $response->json();

        if (! is_array($payload) || empty($payload['id_token']) || ! is_string($payload['id_token'])) {
            throw new RuntimeException('Google token response did not contain an ID token.');
        }

        return $payload;
    }

    public function verifyIdToken(string $idToken, string $clientId, string $nonce): array
    {
        if (! function_exists('openssl_verify')) {
            throw new RuntimeException('OpenSSL is required for Google authentication.');
        }

        if ($idToken === '' || strlen($idToken) > 20000) {
            throw new RuntimeException('Invalid Google ID token.');
        }

        $segments = explode('.', $idToken);

        if (count($segments) !== 3) {
            throw new RuntimeException('Invalid Google ID token structure.');
        }

        $headerJson = $this->base64UrlDecode($segments[0]);
        $claimsJson = $this->base64UrlDecode($segments[1]);
        $signature = $this->base64UrlDecode($segments[2]);

        if ($headerJson === false || $claimsJson === false || $signature === false) {
            throw new RuntimeException('Invalid Google ID token encoding.');
        }

        $header = json_decode($headerJson, true);
        $claims = json_decode($claimsJson, true);

        if (! is_array($header) || ! is_array($claims)) {
            throw new RuntimeException('Invalid Google ID token payload.');
        }

        if (($header['alg'] ?? null) !== 'RS256' || empty($header['kid']) || ! is_string($header['kid'])) {
            throw new RuntimeException('Unsupported Google ID token signature.');
        }

        $jwk = $this->findSigningKey($header['kid']);

        if (! $jwk) {
            $jwk = $this->findSigningKey($header['kid'], true);
        }

        if (! $jwk) {
            throw new RuntimeException('Google signing key was not found.');
        }

        $verified = openssl_verify(
            $segments[0].'.'.$segments[1],
            $signature,
            $this->jwkToPem($jwk),
            OPENSSL_ALGO_SHA256
        );

        if ($verified !== 1) {
            throw new RuntimeException('Google ID token signature verification failed.');
        }

        $this->validateClaims($claims, $clientId, $nonce);

        return $claims;
    }

    private function validateClaims(array $claims, string $clientId, string $nonce): void
    {
        $now = time();

        if (empty($claims['iss']) || ! in_array($claims['iss'], [self::ISSUER, 'accounts.google.com'], true)) {
            throw new RuntimeException('Invalid Google ID token issuer.');
        }

        $audiences = isset($claims['aud']) ? (array) $claims['aud'] : [];

        if (! in_array($clientId, $audiences, true)) {
            throw new RuntimeException('Invalid Google ID token audience.');
        }

        if ((count($audiences) > 1 || isset($claims['azp']))
            && (($claims['azp'] ?? null) !== $clientId)) {
            throw new RuntimeException('Invalid Google ID token authorized party.');
        }

        if (! isset($claims['exp']) || ! is_numeric($claims['exp']) || (int) $claims['exp'] < ($now - 60)) {
            throw new RuntimeException('Expired Google ID token.');
        }

        if (isset($claims['iat']) && (! is_numeric($claims['iat']) || (int) $claims['iat'] > ($now + 300))) {
            throw new RuntimeException('Invalid Google ID token issue time.');
        }

        if (isset($claims['nbf']) && (! is_numeric($claims['nbf']) || (int) $claims['nbf'] > ($now + 60))) {
            throw new RuntimeException('Google ID token is not valid yet.');
        }

        if (! isset($claims['nonce']) || ! is_string($claims['nonce']) || ! hash_equals($nonce, $claims['nonce'])) {
            throw new RuntimeException('Invalid Google ID token nonce.');
        }

        if (empty($claims['sub']) || ! is_string($claims['sub']) || strlen($claims['sub']) > 255) {
            throw new RuntimeException('Invalid Google account identifier.');
        }

        if (empty($claims['email']) || ! is_string($claims['email'])
            || ! filter_var($claims['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Google account did not provide a valid email address.');
        }

        if (! filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('Google email address is not verified.');
        }
    }

    private function findSigningKey(string $keyId, bool $forceRefresh = false): ?array
    {
        $jwks = $forceRefresh ? null : Cache::get(self::JWKS_CACHE_KEY);

        if (! is_array($jwks) || empty($jwks['keys'])) {
            $response = Http::acceptJson()
                ->withOptions(['connect_timeout' => 5])
                ->timeout(12)
                ->get(self::JWKS_ENDPOINT);

            if (! $response->successful() || ! is_array($response->json())) {
                throw new RuntimeException('Google signing keys request failed.');
            }

            $jwks = $response->json();
            Cache::put(self::JWKS_CACHE_KEY, $jwks, now()->addHour());
        }

        foreach ($jwks['keys'] ?? [] as $key) {
            if (($key['kid'] ?? null) !== $keyId
                || ($key['kty'] ?? null) !== 'RSA'
                || empty($key['n'])
                || empty($key['e'])) {
                continue;
            }

            if (isset($key['alg']) && $key['alg'] !== 'RS256') {
                continue;
            }

            if (isset($key['use']) && $key['use'] !== 'sig') {
                continue;
            }

            return $key;
        }

        return null;
    }

    private function jwkToPem(array $jwk): string
    {
        $modulus = $this->base64UrlDecode($jwk['n']);
        $exponent = $this->base64UrlDecode($jwk['e']);

        if ($modulus === false || $exponent === false) {
            throw new RuntimeException('Invalid Google signing key.');
        }

        $rsaPublicKey = $this->asn1Sequence($this->asn1Integer($modulus).$this->asn1Integer($exponent));
        $algorithmIdentifier = hex2bin('300d06092a864886f70d0101010500');
        $bitString = "\x03".$this->asn1Length(strlen($rsaPublicKey) + 1)."\x00".$rsaPublicKey;
        $subjectPublicKeyInfo = $this->asn1Sequence($algorithmIdentifier.$bitString);

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    private function asn1Integer(string $value): string
    {
        $value = ltrim($value, "\x00");

        if ($value === '') {
            $value = "\x00";
        }

        if ((ord($value[0]) & 0x80) !== 0) {
            $value = "\x00".$value;
        }

        return "\x02".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Sequence(string $value): string
    {
        return "\x30".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $encoded = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($encoded)).$encoded;
    }

    private function base64UrlDecode(string $value)
    {
        if ($value === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            return false;
        }

        $remainder = strlen($value) % 4;

        if ($remainder === 1) {
            return false;
        }

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
