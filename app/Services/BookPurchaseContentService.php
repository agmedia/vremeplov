<?php

namespace App\Services;

use App\Models\Back\Settings\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class BookPurchaseContentService
{
    private const CODE = 'store';
    private const KEY = 'book_purchase_content';

    private const FIELDS = [
        'title',
        'meta_title',
        'meta_description',
        'intro_title',
        'intro_html',
        'form_title',
        'full_name_label',
        'postal_code_label',
        'email_label',
        'phone_label',
        'photos_label',
        'photos_help',
        'choose_photos_label',
        'no_photos_label',
        'selected_photos_label',
        'remove_photo_label',
        'consent_text',
        'submit_label',
        'success_message',
    ];

    public function get(): array
    {
        if (! Schema::hasTable('settings')) {
            return $this->defaults();
        }

        return $this->normalize($this->stored());
    }

    public function save(array $data): bool
    {
        if (! Schema::hasTable('settings')) {
            return false;
        }

        $payload = $this->normalize($data);
        $value = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $setting = Settings::query()
            ->where('code', self::CODE)
            ->where('key', self::KEY)
            ->first();

        $saved = $setting
            ? Settings::edit($setting->id, self::CODE, self::KEY, $value, true)
            : Settings::insert(self::CODE, self::KEY, $value, true);

        return (bool) $saved;
    }

    public function defaults(): array
    {
        return [
            'title' => 'Otkup knjiga',
            'meta_title' => 'Otkup knjiga - Antikvarijat Vremeplov Zagreb',
            'meta_description' => 'Ponudite antikvarne i rabljene knjige na otkup Antikvarijatu Vremeplov. Pošaljite svoje podatke i fotografije knjiga.',
            'intro_title' => 'Knjige zaslužuju novi život',
            'intro_html' => '<p>Imate knjige koje više ne čitate, naslijeđenu biblioteku ili zbirku kojoj želite pronaći novog vlasnika? Antikvarijat Vremeplov razmatra ponude antikvarnih, rabljenih i kolekcionarski zanimljivih knjiga.</p><p>Ispunite obrazac i priložite jasne fotografije knjiga. Snimite ih pri dobrom svjetlu tako da se vide hrptovi, naslovnice i opće stanje. Nakon pregleda javit ćemo vam se s informacijom možemo li ponuditi otkup i koji su sljedeći koraci.</p>',
            'form_title' => 'Pošaljite prijavu za otkup',
            'full_name_label' => 'Ime i prezime',
            'postal_code_label' => 'Poštanski broj',
            'email_label' => 'E-mail adresa',
            'phone_label' => 'Kontakt broj',
            'photos_label' => 'Fotografije knjiga',
            'photos_help' => 'Možete učitati do 20 fotografija. Najveća veličina pojedine fotografije je 4 MB, a ukupno 40 MB. Podržani su JPG, PNG, WEBP, HEIC i HEIF formati.',
            'choose_photos_label' => 'Odaberite fotografije',
            'no_photos_label' => 'Nijedna fotografija nije odabrana.',
            'selected_photos_label' => ':count fotografija odabrano',
            'remove_photo_label' => 'Ukloni',
            'consent_text' => 'Slanjem obrasca potvrđujete da se navedeni podaci i fotografije mogu koristiti isključivo radi pregleda ponude i povratnog kontakta.',
            'submit_label' => 'Pošaljite prijavu',
            'success_message' => 'Hvala! Vaša prijava za otkup knjiga uspješno je poslana. Javit ćemo vam se nakon pregleda.',
        ];
    }

    private function stored(): array
    {
        $setting = Settings::get(self::CODE, self::KEY);

        if ($setting instanceof Collection) {
            return json_decode(json_encode($setting->all()), true) ?: [];
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

    private function normalize(array $data): array
    {
        $defaults = $this->defaults();
        $normalized = [];

        foreach (self::FIELDS as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $normalized[$field] = $value !== '' ? $value : $defaults[$field];
        }

        return $normalized;
    }
}
