<?php

namespace App\Services\Mailchimp;

use App\Models\Back\Settings\Settings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MailchimpConnectionSettings
{
    private const CODE = 'marketing';
    private const KEY = 'mailchimp';

    public function connection(): array
    {
        $stored = $this->stored();
        $decryptionFailed = false;
        $apiKey = $this->secret($stored, 'api_key', (string) config('services.mailchimp.api_key', ''), $decryptionFailed);
        $webhookToken = $this->secret($stored, 'webhook_token', (string) config('services.mailchimp.webhook_token', ''), $decryptionFailed);
        $signingSecret = $this->secret($stored, 'webhook_signing_secret', (string) config('services.mailchimp.webhook_signing_secret', ''), $decryptionFailed);
        $server = trim((string) ($stored['server_prefix'] ?? config('services.mailchimp.server_prefix', '')));

        if ($server === '' && preg_match('/-(us[1-9][0-9]{0,2})$/', $apiKey, $matches)) {
            $server = $matches[1];
        }

        return [
            'enabled' => ! $decryptionFailed && filter_var($stored['enabled'] ?? config('services.mailchimp.enabled', false), FILTER_VALIDATE_BOOLEAN),
            'api_key' => $apiKey,
            'server_prefix' => $server !== '' ? $server : 'us1',
            'audience_id' => trim((string) ($stored['audience_id'] ?? config('services.mailchimp.audience_id', ''))),
            // A corrupt signing secret must never downgrade authorization to token-only.
            'webhook_token' => $decryptionFailed ? '' : $webhookToken,
            'webhook_signing_secret' => $signingSecret,
        ];
    }

    public function publicSettings(): array
    {
        $connection = $this->connection();

        return [
            'enabled' => $connection['enabled'],
            'server_prefix' => $connection['server_prefix'],
            'audience_id' => $connection['audience_id'],
            'key_configured' => $connection['api_key'] !== '',
            'webhook_configured' => $connection['webhook_token'] !== '',
            'signing_configured' => $connection['webhook_signing_secret'] !== '',
        ];
    }

    public function save(array $data): bool
    {
        if (! Schema::hasTable('settings')) {
            return false;
        }

        $current = $this->connection();
        $apiKey = trim((string) ($data['api_key'] ?? ''));
        $apiKey = $apiKey !== '' ? $apiKey : $current['api_key'];
        $signingSecret = trim((string) ($data['webhook_signing_secret'] ?? ''));
        $signingSecret = $signingSecret !== '' ? $signingSecret : $current['webhook_signing_secret'];
        $webhookToken = $current['webhook_token'] !== '' ? $current['webhook_token'] : Str::random(64);

        $payload = [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'server_prefix' => trim((string) ($data['server_prefix'] ?? $current['server_prefix'])),
            'audience_id' => trim((string) ($data['audience_id'] ?? $current['audience_id'])),
            'api_key_encrypted' => $apiKey !== '' ? Crypt::encryptString($apiKey) : '',
            'webhook_token_encrypted' => Crypt::encryptString($webhookToken),
            'webhook_signing_secret_encrypted' => $signingSecret !== '' ? Crypt::encryptString($signingSecret) : '',
        ];

        Settings::query()->updateOrCreate([
            'code' => self::CODE,
            'key' => self::KEY,
        ], [
            'value' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'json' => true,
        ]);

        return true;
    }

    public function webhookUrl(): ?string
    {
        $token = $this->connection()['webhook_token'];

        return $token !== '' ? route('mailchimp.webhook', ['token' => $token]) : null;
    }

    private function stored(): array
    {
        try {
            if (! Schema::hasTable('settings')) {
                return ['enabled' => false];
            }

            $value = Settings::query()->where('code', self::CODE)->where('key', self::KEY)->value('value');

            if ($value === null) {
                return [];
            }

            $decoded = json_decode((string) $value, true);

            return is_array($decoded) ? $decoded : ['enabled' => false];
        } catch (Throwable $exception) {
            return ['enabled' => false];
        }
    }

    private function secret(array $stored, string $key, string $fallback, bool &$decryptionFailed): string
    {
        $encryptedKey = $key . '_encrypted';

        if (! array_key_exists($encryptedKey, $stored)) {
            return trim($fallback);
        }

        if ($stored[$encryptedKey] === '') {
            return '';
        }

        try {
            return trim(Crypt::decryptString((string) $stored[$encryptedKey]));
        } catch (Throwable $exception) {
            // A changed application key must fail closed instead of exposing or replacing credentials.
            $decryptionFailed = true;
            return '';
        }
    }
}
