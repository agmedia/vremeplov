<?php

namespace App\Services\Shipping;

use App\Models\Back\Settings\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BoxNowSettingsService
{
    private const CODE = 'shipping';
    private const KEY = 'boxnow_api';

    /** @var array|null */
    private $resolved;

    public function get(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $stored = $this->stored();
        $defaults = $this->defaults();
        $secret = $defaults['client_secret'];

        if (! empty($stored['client_secret_encrypted'])) {
            try {
                $secret = Crypt::decryptString((string) $stored['client_secret_encrypted']);
            } catch (Throwable $exception) {
                Log::warning('Spremljeni Box Now Client Secret nije moguće dešifrirati.', [
                    'exception' => get_class($exception),
                ]);
            }
        }

        return $this->resolved = [
            'base_url' => $this->value($stored, 'base_url', $defaults),
            'client_id' => $this->value($stored, 'client_id', $defaults),
            'client_secret' => trim((string) $secret),
            'api_partner_id' => $this->value($stored, 'api_partner_id', $defaults),
            'widget_partner_id' => (int) ($stored['widget_partner_id'] ?? $defaults['widget_partner_id']),
            'order_prefix' => $this->value($stored, 'order_prefix', $defaults),
            'warehouse_location_id' => $this->value($stored, 'warehouse_location_id', $defaults),
            'origin_name' => $this->value($stored, 'origin_name', $defaults),
            'origin_email' => $this->value($stored, 'origin_email', $defaults),
            'origin_phone' => $this->value($stored, 'origin_phone', $defaults),
            'tracking_url' => $this->value($stored, 'tracking_url', $defaults),
            'allow_return' => filter_var(
                $stored['allow_return'] ?? $defaults['allow_return'],
                FILTER_VALIDATE_BOOLEAN
            ),
            'cod_enabled' => filter_var(
                $stored['cod_enabled'] ?? $defaults['cod_enabled'],
                FILTER_VALIDATE_BOOLEAN
            ),
            'email_label_on_create' => filter_var(
                $stored['email_label_on_create'] ?? $defaults['email_label_on_create'],
                FILTER_VALIDATE_BOOLEAN
            ),
        ];
    }

    public function save(array $data): bool
    {
        if (! Schema::hasTable('settings')) {
            return false;
        }

        $current = $this->get();
        $clientSecret = trim((string) ($data['client_secret'] ?? ''));

        if ($clientSecret === '') {
            $clientSecret = $current['client_secret'];
        }

        $payload = [
            'base_url' => rtrim(trim((string) ($data['base_url'] ?? '')), '/'),
            'client_id' => trim((string) ($data['client_id'] ?? '')),
            'client_secret_encrypted' => $clientSecret !== '' ? Crypt::encryptString($clientSecret) : '',
            'api_partner_id' => trim((string) ($data['api_partner_id'] ?? '')),
            'widget_partner_id' => (int) ($data['widget_partner_id'] ?? 123),
            'order_prefix' => trim((string) ($data['order_prefix'] ?? 'VREMEPLOV')),
            'warehouse_location_id' => trim((string) ($data['warehouse_location_id'] ?? '')),
            'origin_name' => trim((string) ($data['origin_name'] ?? '')),
            'origin_email' => trim((string) ($data['origin_email'] ?? '')),
            'origin_phone' => trim((string) ($data['origin_phone'] ?? '')),
            'tracking_url' => trim((string) ($data['tracking_url'] ?? '')),
            'allow_return' => (bool) ($data['allow_return'] ?? true),
            'cod_enabled' => (bool) ($data['cod_enabled'] ?? false),
            'email_label_on_create' => (bool) ($data['email_label_on_create'] ?? true),
        ];

        $value = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $setting = Settings::query()
            ->where('code', self::CODE)
            ->where('key', self::KEY)
            ->first();
        $saved = $setting
            ? Settings::edit($setting->id, self::CODE, self::KEY, $value, true)
            : Settings::insert(self::CODE, self::KEY, $value, true);

        if ($saved) {
            $this->resolved = null;
        }

        return (bool) $saved;
    }

    public function adminValues(): array
    {
        $values = $this->get();

        return [
            'base_url' => $values['base_url'],
            'client_id' => $values['client_id'],
            'has_client_secret' => $values['client_secret'] !== '',
            'api_partner_id' => $values['api_partner_id'],
            'widget_partner_id' => $values['widget_partner_id'] ?: 123,
            'order_prefix' => $values['order_prefix'],
            'warehouse_location_id' => $values['warehouse_location_id'],
            'origin_name' => $values['origin_name'],
            'origin_email' => $values['origin_email'],
            'origin_phone' => $values['origin_phone'],
            'tracking_url' => $values['tracking_url'],
            'allow_return' => $values['allow_return'],
            'cod_enabled' => $values['cod_enabled'],
            'email_label_on_create' => $values['email_label_on_create'],
        ];
    }

    public function missingConfiguration(): array
    {
        $values = $this->get();
        $required = [
            'API URL' => $values['base_url'],
            'Client ID' => $values['client_id'],
            'Client Secret' => $values['client_secret'],
            'Widget Partner ID' => $values['widget_partner_id'],
            'prefiks broja narudžbe' => $values['order_prefix'],
            'ID polaznog skladišta' => $values['warehouse_location_id'],
            'naziv pošiljatelja' => $values['origin_name'],
            'e-mail pošiljatelja' => $values['origin_email'],
            'telefon pošiljatelja' => $values['origin_phone'],
            'tracking URL' => $values['tracking_url'],
        ];

        return collect($required)
            ->filter(function ($value) {
                return trim((string) $value) === '';
            })
            ->keys()
            ->values()
            ->all();
    }

    public function isConfigured(): bool
    {
        return $this->missingConfiguration() === [];
    }

    public function isAllowedApiUrl(?string $url = null): bool
    {
        $url = trim((string) ($url ?? $this->get()['base_url']));
        $parts = parse_url($url);

        if (! is_array($parts) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $officialHost = $host === 'boxnow.hr' || str_ends_with($host, '.boxnow.hr');

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && $officialHost
            && $path === '/api/v1'
            && (! isset($parts['port']) || (int) $parts['port'] === 443)
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['query'])
            && ! isset($parts['fragment']);
    }

    private function defaults(): array
    {
        return [
            'base_url' => (string) config('services.boxnow.base_url', 'https://api-production.boxnow.hr/api/v1'),
            'client_id' => (string) config('services.boxnow.client_id', ''),
            'client_secret' => (string) config('services.boxnow.client_secret', ''),
            'api_partner_id' => (string) config('services.boxnow.api_partner_id', ''),
            'widget_partner_id' => (int) config('services.boxnow.widget_partner_id', 123),
            'order_prefix' => (string) config('services.boxnow.order_prefix', 'VREMEPLOV'),
            'warehouse_location_id' => (string) config('services.boxnow.warehouse_location_id', ''),
            'origin_name' => (string) config('services.boxnow.origin_name', ''),
            'origin_email' => (string) config('services.boxnow.origin_email', ''),
            'origin_phone' => (string) config('services.boxnow.origin_phone', ''),
            'tracking_url' => (string) config('services.boxnow.tracking_url', 'https://track.boxnow.hr/en?track={parcel}'),
            'allow_return' => (bool) config('services.boxnow.allow_return', true),
            'cod_enabled' => (bool) config('services.boxnow.cod_enabled', false),
            'email_label_on_create' => (bool) config('services.boxnow.email_label_on_create', true),
        ];
    }

    private function stored(): array
    {
        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            $setting = Settings::get(self::CODE, self::KEY);
        } catch (Throwable $exception) {
            return [];
        }

        if ($setting instanceof Collection) {
            return $setting->toArray();
        }

        if (is_array($setting)) {
            return $setting;
        }

        if (is_object($setting)) {
            return json_decode(json_encode($setting), true) ?: [];
        }

        if (is_string($setting)) {
            return json_decode($setting, true) ?: [];
        }

        return [];
    }

    private function value(array $stored, string $key, array $defaults): string
    {
        return trim((string) (array_key_exists($key, $stored) ? $stored[$key] : $defaults[$key]));
    }
}
