<?php

namespace App\Services\Payments;

use App\Models\Back\Settings\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalReportingClient
{
    private const BASE_URLS = [
        'test' => 'https://api-m.sandbox.paypal.com',
        'live' => 'https://api-m.paypal.com',
    ];

    public function search(string $environment, CarbonInterface $start, CarbonInterface $end): array
    {
        $credentials = $this->credentials($environment);
        $token = $this->accessToken($environment, $credentials);
        $page = 1;
        $transactions = [];

        do {
            $response = Http::withToken($token)
                ->acceptJson()
                ->withOptions(['verify' => true, 'allow_redirects' => false])
                ->timeout((int) config('paypal.reconciliation.timeout_seconds', 10))
                ->get(self::BASE_URLS[$environment] . '/v1/reporting/transactions', [
                    'start_date' => $start->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                    'end_date' => $end->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                    'balance_affecting_records_only' => 'Y',
                    'page_size' => (int) config('paypal.reconciliation.page_size', 500),
                    'page' => $page,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('PayPal Reporting API returned HTTP ' . $response->status() . '.');
            }

            $body = $response->json();
            if (! is_array($body)) {
                throw new RuntimeException('PayPal Reporting API returned an invalid response.');
            }

            $details = $body['transaction_details'] ?? [];
            if (! is_array($details)) {
                throw new RuntimeException('PayPal Reporting API returned invalid transaction data.');
            }

            $transactions = array_merge($transactions, $details);
            $totalPages = max(1, (int) ($body['total_pages'] ?? 1));
            $page++;
        } while ($page <= min($totalPages, 100));

        return [
            'transactions' => $transactions,
        ];
    }

    private function accessToken(string $environment, array $credentials): string
    {
        $cacheKey = 'paypal-reporting-token:' . $environment . ':' . hash('sha256', $credentials['client_id']);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->withBasicAuth($credentials['client_id'], $credentials['client_secret'])
            ->acceptJson()
            ->withOptions(['verify' => true, 'allow_redirects' => false])
            ->timeout((int) config('paypal.reconciliation.timeout_seconds', 10))
            ->post(self::BASE_URLS[$environment] . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        $token = $response->successful() ? trim((string) $response->json('access_token')) : '';
        if ($token === '') {
            throw new RuntimeException('PayPal OAuth failed with HTTP ' . $response->status() . '.');
        }

        $ttl = max(60, ((int) $response->json('expires_in', 300)) - 60);
        Cache::put($cacheKey, $token, now()->addSeconds($ttl));

        return $token;
    }

    private function credentials(string $environment): array
    {
        if (! isset(self::BASE_URLS[$environment])) {
            throw new RuntimeException('Unknown PayPal environment.');
        }

        $setting = Settings::get('payment', 'list.paypal');
        $paypal = $setting instanceof \Illuminate\Support\Collection
            ? $setting->first(function ($item) {
                return strtolower((string) data_get($item, 'code')) === 'paypal';
            }) ?: $setting->first()
            : null;
        $prefix = $environment === 'test' ? 'test' : 'live';
        $configEnvironment = $environment === 'test' ? 'sandbox' : 'live';
        $clientId = trim((string) (data_get($paypal, 'data.' . $prefix . '_id')
            ?: config('paypal.' . $configEnvironment . '.client_id')));
        $clientSecret = trim((string) (data_get($paypal, 'data.' . $prefix . '_secret')
            ?: config('paypal.' . $configEnvironment . '.client_secret')));
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('PayPal Reporting credentials are not configured for ' . $environment . '.');
        }

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];
    }
}
