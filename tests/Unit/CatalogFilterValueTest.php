<?php

namespace Tests\Unit;

use App\Support\CatalogFilterValue;
use PHPUnit\Framework\TestCase;

class CatalogFilterValueTest extends TestCase
{
    public function test_display_removes_double_encoded_html_and_trailing_punctuation(): void
    {
        $this->assertSame(
            'LATINICA',
            CatalogFilterValue::display('&amp;lt;span&amp;gt;LATINICA,&amp;lt;/span&amp;gt;')
        );
    }

    /**
     * @dataProvider legacyLetterProvider
     */
    public function test_letter_facet_maps_controlled_typos_and_combinations(
        string $legacy,
        string $canonical
    ): void {
        $this->assertSame($canonical, CatalogFilterValue::facetDisplay('letter', $legacy));
    }

    public function legacyLetterProvider(): array
    {
        return [
            'stray prefix before cyrillic' => ['LĆIRILICA', 'Ćirilica'],
            'cyrillic typo' => ['ĆIRIKICA', 'Ćirilica'],
            'latin typo' => ['LATIBICA', 'Latinica'],
            'latin two-character typo' => ['LSTINICS', 'Latinica'],
            'numeric suffix' => ['LATINICA1', 'Latinica'],
            'pasted text after script' => [
                'LATINICAMLADEN MACHIEDO, NOVI TALIJANSKI PJESNICI',
                'Latinica',
            ],
            'stable combination order' => [
                'ćirilica, hanzi, LATINICA, ćirilica',
                'Latinica, Ćirilica, Hanzi',
            ],
            'explicit scripts' => [
                'hebrejsko pismo, arapsko pismo, devanagari, hiragana, katakana, kanji',
                'Hebrejsko, Arapsko, Devanagari, Hiragana, Katakana, Kanji',
            ],
            'unknown language in script column is hidden' => ['SRPSKI', ''],
            'ordinary qualifier words are not fuzzy scripts' => ['godina, korica, stranica', ''],
        ];
    }

    public function test_destructive_and_person_keys_preserve_diacritics(): void
    {
        $this->assertNotSame(
            CatalogFilterValue::entityKey('Ivan Sarić'),
            CatalogFilterValue::entityKey('Ivan Šarić')
        );
        $this->assertNotSame(
            CatalogFilterValue::personKey('Ivan Sarić'),
            CatalogFilterValue::personKey('Ivan Šarić')
        );
    }

    public function test_person_name_matching_groups_reversed_names_and_initials(): void
    {
        $this->assertTrue(CatalogFilterValue::personNamesMatch('Paulo Coelho', 'COELHO PAULO'));
        $this->assertTrue(CatalogFilterValue::personNamesMatch('Carl Gustav Jung', 'C. G. Jung'));
        $this->assertFalse(CatalogFilterValue::personNamesMatch('Carl Gustav Jung', 'Chang Jung'));
        $this->assertFalse(CatalogFilterValue::personNamesMatch('Ivan Sarić', 'Ivan Šarić'));
    }

    public function test_person_groups_choose_a_human_readable_label(): void
    {
        $groups = CatalogFilterValue::groupPeople(collect([
            (object) ['title' => 'COELHO PAULO'],
            (object) ['title' => 'Paulo Coelho'],
            (object) ['title' => 'Chang Jung'],
        ]));

        $this->assertCount(2, $groups);
        $preferred = $groups[0]
            ->sortByDesc(fn ($author) => CatalogFilterValue::personLabelScore($author->title))
            ->first();
        $this->assertSame('Paulo Coelho', $preferred->title);
    }

    public function test_facet_values_are_atomic_and_use_sentence_case(): void
    {
        $this->assertSame(
            ['Latinica', 'Ćirilica'],
            CatalogFilterValue::facetValues('letter', 'ĆIRILICA / LATINICA')
        );
        $this->assertSame(
            ['Hrvatski', 'Engleski', 'Njemački'],
            CatalogFilterValue::facetValues('origin', 'HRVATSKI, engleski i NJEMAČKI')
        );
        $this->assertSame(
            ['Tvrdi s ovitkom'],
            CatalogFilterValue::facetValues('binding', 'TVRDI UVEZ S OVITKOM')
        );
    }

    public function test_detailed_condition_has_a_runtime_bucket_but_non_lossy_storage_value(): void
    {
        $this->assertSame(
            'Dobro',
            CatalogFilterValue::facetDisplay('condition', 'dobro, s posvetom')
        );
        $this->assertSame(
            'dobro, s posvetom',
            CatalogFilterValue::storageDisplay('condition', 'dobro, s posvetom')
        );
        $this->assertSame(
            'Dobro',
            CatalogFilterValue::facetDisplay(
                'condition',
                'dobro, knjiga je potpuno nova'
            ),
            'The first anchored grade must win over text later in the description.'
        );
        $this->assertSame([], CatalogFilterValue::facetValues('condition', '1. svezak'));
        $this->assertSame(
            '1. svezak',
            CatalogFilterValue::storageDisplay('condition', '1. svezak')
        );
        $this->assertSame(
            'Vrlo dobro',
            CatalogFilterValue::storageDisplay('condition', ' VRLO DOBRO, ')
        );
        $this->assertSame(
            'Rabljena, očuvana knjiga',
            CatalogFilterValue::facetDisplay('condition', 'rabljena, očuvana knjiga')
        );
        $this->assertSame(
            'Korišteno',
            CatalogFilterValue::facetDisplay('condition', 'rabljena, loše očuvana')
        );
        $this->assertSame(
            'Korišteno',
            CatalogFilterValue::facetDisplay('condition', 'rabljena, neočuvana')
        );
    }

    public function test_binding_uses_conservative_tokens_and_keeps_descriptions_for_storage(): void
    {
        $this->assertSame([], CatalogFilterValue::facetValues('binding', 'tvrdi, meki'));
        $this->assertSame(
            [],
            CatalogFilterValue::facetValues('binding', 'TVRDO PAKIRANJE, MEKI UVEZ KNJIGE')
        );
        $this->assertSame([], CatalogFilterValue::facetValues('binding', 'tvrđava'));
        $this->assertSame(
            'MEKI, PREUKORIČENO',
            CatalogFilterValue::storageDisplay('binding', 'MEKI, PREUKORIČENO')
        );
        $this->assertSame('Tvrdi', CatalogFilterValue::storageDisplay('binding', 'TVRDI'));
        $this->assertSame('Tvrdi', CatalogFilterValue::storageDisplay('binding', 'Tvrde korice'));
        $this->assertSame('Meki', CatalogFilterValue::storageDisplay('binding', 'Meki uvez'));
    }

    public function test_origin_rejects_ambiguous_or_non_language_values(): void
    {
        $this->assertSame([], CatalogFilterValue::facetValues('origin', '-'));
        $this->assertSame([], CatalogFilterValue::facetValues('origin', 'arapski ?'));
        $this->assertSame([], CatalogFilterValue::facetValues('origin', 'Hrvatska'));
        $this->assertSame([], CatalogFilterValue::facetValues('origin', 'HINDU'));
        $this->assertSame([], CatalogFilterValue::facetValues('origin', 'hrvatski1936'));
        $this->assertSame([], CatalogFilterValue::facetValues('origin', 'latinica'));
        $this->assertSame([], CatalogFilterValue::facetValues('origin', 'bilješka en'));
        $this->assertSame(['Engleski'], CatalogFilterValue::facetValues('origin', 'EN'));
        $this->assertSame(
            'hrvatski en',
            CatalogFilterValue::storageDisplay('origin', 'hrvatski en')
        );
        $this->assertSame(
            ['Hrvatski', 'Srpski'],
            CatalogFilterValue::facetValues('origin', 'srpskohrvatski')
        );
        $this->assertSame(
            ['Hrvatski', 'Engleski', 'Njemački'],
            CatalogFilterValue::facetValues('origin', 'HRAVTSKI, engliski, njemčaki')
        );
        $this->assertSame(
            'Hrvatski, latinica',
            CatalogFilterValue::storageDisplay('origin', 'Hrvatski, latinica')
        );
    }

    public function test_selected_languages_are_stored_in_stable_canonical_order(): void
    {
        $this->assertSame(
            'Hrvatski, Engleski, Njemački',
            CatalogFilterValue::storageDisplay('origin', 'Njemački, Hrvatski, Engleski')
        );
    }
}
