<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasNormalizedUniqueTitle
{
    /**
     * Normalizes names entered by administrators and import jobs.
     */
    public static function normalizeTitle(?string $title): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $title)) ?: '';
    }

    /**
     * Finds the same name regardless of casing, surrounding/repeated spaces,
     * or common diacritic and punctuation differences represented by the slug.
     */
    public static function findByEquivalentTitle(?string $title)
    {
        $title = static::normalizeTitle($title);

        if ($title === '') {
            return null;
        }

        $normalized = mb_strtolower($title, 'UTF-8');
        $slug = Str::slug($title);

        return static::query()
            ->where(function ($query) use ($normalized, $slug) {
                $query->whereRaw('LOWER(TRIM(title)) = ?', [$normalized]);

                if ($slug !== '') {
                    $query->orWhere('slug', $slug);
                }
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * Laravel validation callback shared by the regular author/publisher forms.
     */
    public function rejectEquivalentTitle(string $attribute, $value, callable $fail): void
    {
        $existing = static::findByEquivalentTitle($value);

        if ($existing && (int) $existing->getKey() !== (int) $this->getKey()) {
            $fail('Ovaj naziv već postoji. Odaberite postojeći zapis.');
        }
    }
}
