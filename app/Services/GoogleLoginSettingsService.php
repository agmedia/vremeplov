<?php

namespace App\Services;

use App\Models\Back\Settings\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GoogleLoginSettingsService
{
    private const CODE = 'auth';
    private const KEY = 'google_login';

    public function get(): array
    {
        $stored = $this->stored();
        $defaults = $this->defaults();
        $secret = $defaults['client_secret'];

        if (! empty($stored['client_secret_encrypted'])) {
            try {
                $secret = Crypt::decryptString((string) $stored['client_secret_encrypted']);
            } catch (Throwable $exception) {
                Log::warning('Spremljeni Google Login Client Secret nije moguće dešifrirati.', [
                    'exception' => get_class($exception),
                ]);
                $secret = '';
            }
        }

        return [
            'enabled' => filter_var($stored['enabled'] ?? $defaults['enabled'], FILTER_VALIDATE_BOOLEAN),
            'client_id' => trim((string) ($stored['client_id'] ?? $defaults['client_id'])),
            'client_secret' => trim((string) $secret),
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
            'enabled' => (bool) ($data['enabled'] ?? false),
            'client_id' => trim((string) ($data['client_id'] ?? '')),
            'client_secret_encrypted' => $clientSecret !== ''
                ? Crypt::encryptString($clientSecret)
                : '',
        ];

        $value = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $setting = Settings::query()
            ->where('code', self::CODE)
            ->where('key', self::KEY)
            ->first();

        $saved = $setting
            ? Settings::edit($setting->id, self::CODE, self::KEY, $value, true)
            : Settings::insert(self::CODE, self::KEY, $value, true);

        return (bool) $saved;
    }

    public function enabled(): bool
    {
        $settings = $this->get();

        return $settings['enabled']
            && $this->validClientId($settings['client_id'])
            && $settings['client_secret'] !== '';
    }

    public function validClientId(?string $clientId): bool
    {
        $clientId = trim((string) $clientId);

        return $clientId !== '' && str_ends_with($clientId, '.apps.googleusercontent.com');
    }

    private function defaults(): array
    {
        return [
            'enabled' => (bool) config('services.google_login.enabled', false),
            'client_id' => (string) config('services.google_login.client_id', ''),
            'client_secret' => (string) config('services.google_login.client_secret', ''),
        ];
    }

    private function stored(): array
    {
        try {
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
}
