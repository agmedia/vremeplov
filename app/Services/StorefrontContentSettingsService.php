<?php

namespace App\Services;

use App\Models\Back\Settings\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class StorefrontContentSettingsService
{
    public const CODE = 'app';

    public const KEY = 'storefront_content';

    private const DEFAULTS = [
        'announcement_text' => 'Besplatna dostava U RH za narudžbe iznad 70 €',
        'footer_title' => 'Antikvarijat Vremeplov',
        'footer_address' => 'Zvonimirova 24',
        'footer_postal_code' => '10000',
        'footer_city' => 'Zagreb',
        'footer_phone' => '091 762 7441',
        'footer_weekday_hours' => 'Pon-Pet: 09 - 14h i 16 - 19h',
        'footer_saturday_hours' => 'Sub: 10 - 13h',
        'instagram_url' => 'https://www.instagram.com/antikvarijatvremeplov',
        'facebook_url' => 'https://www.facebook.com/antikavrijatvremeplov',
    ];

    private ?array $resolved = null;

    public function defaults(): array
    {
        return self::DEFAULTS;
    }

    public function get(?Collection $items = null): array
    {
        if ($items === null && $this->resolved !== null) {
            return $this->resolved;
        }

        $setting = $items?->firstWhere('key', self::KEY);

        if ($items === null && Schema::hasTable('settings')) {
            $setting = Settings::query()
                ->where('code', self::CODE)
                ->where('key', self::KEY)
                ->first();
        }

        $values = $this->decode($setting?->value);
        $resolved = array_replace(self::DEFAULTS, array_intersect_key($values, self::DEFAULTS));
        $resolved['footer_phone_href'] = $this->phoneHref($resolved['footer_phone']);

        if ($items === null) {
            $this->resolved = $resolved;
        }

        return $resolved;
    }

    public function save(array $values): bool
    {
        $stored = (bool) Settings::reset(self::CODE, self::KEY, $values);

        if ($stored) {
            $this->resolved = null;
        }

        return $stored;
    }

    private function decode(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        if (isset($decoded[0]) && is_array($decoded[0])) {
            $decoded = $decoded[0] ?? [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function phoneHref(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone) ?: '';

        if (str_starts_with($phone, '00')) {
            return '+' . substr($phone, 2);
        }

        if (str_starts_with($phone, '0')) {
            return '+385' . substr($phone, 1);
        }

        return $phone;
    }
}
