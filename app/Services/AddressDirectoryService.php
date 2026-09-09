<?php

namespace App\Services;

use Illuminate\Support\Str;

class AddressDirectoryService
{
    public function findByPostal(string $postalCode, string $country = 'Croatia'): ?array
    {
        if (! $this->isCroatia($country)) {
            return null;
        }

        $postalCode = preg_replace('/\D+/', '', $postalCode) ?: '';
        if ($postalCode === '') {
            return null;
        }

        $city = $this->places()[$postalCode] ?? null;

        return $city ? $this->place($postalCode, $city) : null;
    }

    public function findByCity(string $city, string $country = 'Croatia'): ?array
    {
        if (! $this->isCroatia($country)) {
            return null;
        }

        $normalizedCity = $this->normalizeText($city);
        if ($normalizedCity === '') {
            return null;
        }

        foreach ($this->places() as $postalCode => $placeCity) {
            if ($this->normalizeText($placeCity) === $normalizedCity) {
                return $this->place((string) $postalCode, $placeCity);
            }
        }

        return null;
    }

    private function places(): array
    {
        return (array) config('hr_places', []);
    }

    private function place(string $postalCode, string $city): array
    {
        return [
            'postal_code' => $postalCode,
            'city' => $city,
            'country_code' => 'HR',
        ];
    }

    private function isCroatia(string $country): bool
    {
        $country = trim($country);

        return strtoupper($country) === 'HR'
            || in_array($this->normalizeText($country), ['croatia', 'hrvatska'], true);
    }

    private function normalizeText(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }
}
