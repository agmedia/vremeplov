<?php

namespace App\Support;

use Illuminate\Support\Str;

final class CatalogFilterValue
{
    private const LETTER_SCRIPTS = [
        'Latinica' => 'latinica',
        'Ćirilica' => 'cirilica',
        'Gotica' => 'gotica',
        'Glagoljica' => 'glagoljica',
        'Hebrejsko' => 'hebrej',
        'Arapsko' => 'arapsk',
        'Devanagari' => 'devanagari',
        'Hiragana' => 'hiragana',
        'Katakana' => 'katakana',
        'Kanji' => 'kanji',
        'Hanzi' => 'hanzi',
    ];

    private const CONDITION_OPTIONS = [
        'Nova knjiga',
        'Odlično',
        'Vrlo dobro',
        'Dobro',
        'Korišteno',
        'Loše',
        'Rabljena, očuvana knjiga',
    ];

    private const BINDING_OPTIONS = [
        'Tvrdi',
        'Meki',
        'Polutvrdi',
        'Tvrdi s ovitkom',
        'Meki s ovitkom',
    ];

    private const ORIGIN_LANGUAGES = [
        'Hrvatski' => [
            'hrvatski', 'hr', 'hratski', 'hravtski', 'hrtatski', 'hrtvaski',
            'hrtvatski', 'hrvarski', 'hrvartski', 'hrvaski', 'hrvastki',
            'hrvatki', 'hrvatksi', 'hrvatsi', 'hrvatsk', 'hrvatske', 'hrvatskii',
            'hrvatskl', 'hrvatskli', 'hrvatsli', 'hrvatsski', 'hrvtaski',
            'hrvtatski', 'htvatski', 'hvatski', 'hvratski', 'krvatski', 'fhrvatski',
        ],
        'Srpski' => ['srpski', 'srbski', 'sprski', 'serpski', 'srski', 'srsski', 'xrpski', 'hsrpski', 'srp'],
        'Bosanski' => ['bosanski'],
        'Crnogorski' => ['crnogorski'],
        'Slovenski' => ['slovenski', 'slpovenski'],
        'Makedonski' => ['makedonski'],
        'Bugarski' => ['bugarski'],
        'Albanski' => ['albanski'],
        'Engleski' => ['engleski', 'engleki', 'engliski', 'hengleski', 'englesko', 'english', 'en'],
        'Njemački' => ['njemacki', 'njemcaki', 'njemcki', 'njemackii', 'nemacki', 'german'],
        'Francuski' => ['francuski', 'french', 'fr'],
        'Talijanski' => ['talijanski', 'talijansi', 'taljanski', 'taijanski', 'italian'],
        'Španjolski' => ['spanjolski', 'spansjolski', 'spsnjolski', 'spanish', 'es'],
        'Portugalski' => ['portugalski', 'portuguese'],
        'Latinski' => ['latinski'],
        'Grčki' => ['grcki'],
        'Starogrčki' => ['starogrcki'],
        'Ruski' => ['ruski'],
        'Ukrajinski' => ['ukrajinski'],
        'Poljski' => ['poljski'],
        'Češki' => ['ceski'],
        'Slovački' => ['slovacki'],
        'Mađarski' => ['madarski', 'madjarski', 'madzarski'],
        'Nizozemski' => ['nizozemski'],
        'Rumunjski' => ['rumunjski'],
        'Švedski' => ['svedski'],
        'Danski' => ['danski'],
        'Norveški' => ['norveski'],
        'Finski' => ['finski'],
        'Turski' => ['turski'],
        'Arapski' => ['arapski', 'arabic'],
        'Hebrejski' => ['hebrejski', 'hebrew'],
        'Perzijski' => ['perzijski', 'persian'],
        'Urdu' => ['urdu'],
        'Hindski' => ['hindski', 'hindi'],
        'Kineski' => ['kineski', 'chinese'],
        'Japanski' => ['japanski', 'japanese'],
        'Korejski' => ['korejski', 'korean'],
        'Sanskrt' => ['sanskrt'],
        'Esperanto' => ['esperanto'],
        'Crkvenoslavenski' => ['crkvenoslavenski'],
        'Višejezično' => ['visejezicno', 'sedmojezicno', 'devetojezicno'],
    ];

    /**
     * Clean a value for display without changing its meaning.
     */
    public static function display($value): string
    {
        $value = (string) $value;

        // Some legacy imports contain entities encoded more than once. Clean
        // after every decode pass so a decoded tag can never reach the UI.
        for ($pass = 0; $pass < 5; $pass++) {
            $cleaned = preg_replace('/<br\s*\/?\s*>/i', ' ', $value) ?? '';
            $cleaned = strip_tags($cleaned);
            $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($cleaned === $value) {
                $value = $cleaned;
                break;
            }

            $value = $cleaned;
        }

        $value = strip_tags(preg_replace('/<br\s*\/?\s*>/i', ' ', $value) ?? '');
        $value = str_replace("\u{00A0}", ' ', $value);
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return trim($value, " \t\n\r\0\x0B,.;:");
    }

    /**
     * Stable comparison key used to collapse punctuation/case variants.
     */
    public static function key($value): string
    {
        $value = Str::lower(Str::ascii(self::display($value)));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /**
     * Canonical display value for one product facet. The legacy `letter`
     * column contains many typos and pasted qualifiers, so only its controlled
     * script vocabulary is allowed through.
     */
    public static function facetDisplay(string $column, $value): string
    {
        return implode(', ', self::facetValues($column, $value));
    }

    /**
     * Return atomic canonical values so combinations contribute to each
     * matching frontend filter bucket independently.
     */
    public static function facetValues(string $column, $value): array
    {
        switch ($column) {
            case 'letter':
                return self::letterFacetValues($value);
            case 'condition':
                $condition = self::conditionFacetDisplay($value);

                return $condition === '' ? [] : [$condition];
            case 'binding':
                $binding = self::bindingFacetDisplay($value);

                return $binding === '' ? [] : [$binding];
            case 'origin':
                return self::originFacetValues($value);
            default:
                $display = self::sentenceCase(self::display($value));

                return $display === '' ? [] : [$display];
        }
    }

    public static function facetKey(string $column, $value): string
    {
        return self::key(self::facetDisplay($column, $value));
    }

    /**
     * Non-lossy cleanup used when persisting legacy data. Detailed condition,
     * binding, and language notes stay intact; only a fully controlled simple
     * value is replaced by its canonical label. `letter` is an enumeration and
     * can therefore be canonicalized strictly.
     */
    public static function storageDisplay(string $column, $value): string
    {
        $display = self::display($value);

        if ($column === 'letter') {
            return self::facetDisplay($column, $display);
        }

        $plain = self::asciiWords($display);

        if ($column === 'condition' && self::isSimpleCondition($plain)) {
            return self::facetDisplay($column, $display);
        }

        if ($column === 'binding' && self::isSimpleBinding($plain)) {
            return self::facetDisplay($column, $display);
        }

        if ($column === 'origin' && self::isSimpleOrigin($display, $plain)) {
            return self::facetDisplay($column, $display);
        }

        return $display;
    }

    public static function storageKey(string $column, $value): string
    {
        return self::key(self::storageDisplay($column, $value));
    }

    /**
     * Canonical values shared by admin inputs, runtime facets, and cleanup.
     */
    public static function facetOptions(string $column): array
    {
        switch ($column) {
            case 'letter':
                return array_keys(self::LETTER_SCRIPTS);
            case 'condition':
                return self::CONDITION_OPTIONS;
            case 'binding':
                return self::BINDING_OPTIONS;
            case 'origin':
                return array_keys(self::ORIGIN_LANGUAGES);
            default:
                return [];
        }
    }

    private static function letterFacetValues($value): array
    {
        $plain = Str::lower(Str::ascii(self::display($value)));
        $tokens = array_values(array_filter(preg_split('/[^a-z0-9]+/', $plain) ?: []));
        $found = [];

        foreach ($tokens as $token) {
            foreach (self::LETTER_SCRIPTS as $label => $pattern) {
                if (in_array($label, ['Latinica', 'Ćirilica', 'Gotica', 'Glagoljica'], true)) {
                    if (self::matchesCoreScriptToken($token, $pattern)) {
                        $found[$label] = true;
                    }

                    continue;
                }

                if (strpos($token, $pattern) === 0) {
                    $found[$label] = true;
                }
            }
        }

        return array_values(array_filter(
            array_keys(self::LETTER_SCRIPTS),
            fn (string $label) => isset($found[$label])
        ));
    }

    private static function conditionFacetDisplay($value): string
    {
        $plain = self::asciiWords($value);

        if ($plain === '') {
            return '';
        }

        if (preg_match('/^(?:nova knjiga|nov[ao]|zapakiran[ao]|potpuno nov[ao])\b/', $plain)) {
            return 'Nova knjiga';
        }

        if (preg_match('/^(?:odlicno|izvrsno|kao novo)\b/', $plain)) {
            return 'Odlično';
        }

        if (preg_match('/^(?:vrlo\s*dobro|vrlodobro)\b/', $plain)) {
            return 'Vrlo dobro';
        }

        if (preg_match('/^(?:dobro|dobr|kdobro)\b/', $plain)) {
            return 'Dobro';
        }

        if (preg_match(
            '/^rabljena(?: knjiga)?(?: i| ali)? ocuvana(?: knjiga)?\b/',
            $plain
        )) {
            return 'Rabljena, očuvana knjiga';
        }

        if (preg_match('/^(?:d?koristen(?:o|a)?|jako koristen(?:o|a)?)\b/', $plain)
            || preg_match('/^(?:rabljeno|rabljena)\b/', $plain)
            || preg_match('/^knjiga je rabljena\b/', $plain)) {
            return 'Korišteno';
        }

        if (preg_match('/^lose\b/', $plain)) {
            return 'Loše';
        }

        if (preg_match('/^knjiga je (?:potpuno )?nova\b/', $plain)) {
            return 'Nova knjiga';
        }

        return '';
    }

    private static function isSimpleCondition(string $plain): bool
    {
        return preg_match(
            '/^(?:nova knjiga|nov[ao](?: knjiga)?|zapakirana (?:nova )?knjiga|potpuno nova knjiga|'
            . 'odlicno|izvrsno|kao novo|vrlo dobro|vrlodobro|dobro|dobr|kdobro|'
            . 'd?koristen(?:o|a)?|jako koristen(?:o|a)?|rabljeno|rabljena|'
            . 'rabljena ocuvana knjiga|lose)$/',
            $plain
        ) === 1;
    }

    private static function bindingFacetDisplay($value): string
    {
        $plain = self::asciiWords($value);

        if ($plain === '') {
            return '';
        }

        $tokens = preg_split('/\s+/', $plain) ?: [];
        $hardTokens = ['tvrd', 'tvrdi', 'tvrda', 'tvrdo', 'tvrde', 'tvrdih', 'tvrdim'];
        $softTokens = ['mek', 'meki', 'meka', 'meko', 'meke', 'mekih', 'mekim'];
        $semiHardTokens = ['polutvrd', 'polutvrdi', 'polutvrda', 'polutvrdo'];
        $hasHard = count(array_intersect($tokens, $hardTokens)) > 0;
        $hasSoft = count(array_intersect($tokens, $softTokens)) > 0;

        if ($hasHard && $hasSoft) {
            return '';
        }

        if (preg_match('/^polu\s+tvrdi\b/', $plain)) {
            return 'Polutvrdi';
        }

        $first = '';

        foreach ($tokens as $token) {
            if (preg_match('/[a-z]/', $token)) {
                $first = $token;
                break;
            }
        }

        $binding = '';

        if (in_array($first, $semiHardTokens, true)) {
            $binding = 'Polutvrdi';
        } elseif (in_array($first, $hardTokens, true)) {
            $binding = 'Tvrdi';
        } elseif (in_array($first, $softTokens, true)) {
            $binding = 'Meki';
        }

        if (($binding === 'Tvrdi' || $binding === 'Meki')
            && preg_match('/\b(?:s|sa) ovit(?:ak|kom)\b/', $plain)) {
            return $binding . ' s ovitkom';
        }

        return $binding;
    }

    private static function isSimpleBinding(string $plain): bool
    {
        $canonical = self::bindingFacetDisplay($plain);

        if ($canonical === '') {
            return false;
        }

        if (strpos($canonical, 's ovitkom') !== false) {
            return preg_match('/^(?:tvrdi|meki)(?: uvez)? (?:s|sa) ovit(?:ak|kom)$/', $plain) === 1;
        }

        return in_array($plain, [
            'tvrd', 'tvrdi', 'tvrda', 'tvrdo', 'tvrde', 'tvrdih', 'tvrdim',
            'tvrdi uvez', 'tvrde korice',
            'mek', 'meki', 'meka', 'meko', 'meke', 'mekih', 'mekim',
            'meki uvez', 'meke korice',
            'polutvrd', 'polutvrdi', 'polutvrda', 'polutvrdo', 'polu tvrdi',
        ], true);
    }

    private static function originFacetValues($value): array
    {
        $display = self::display($value);

        if (preg_match('/[?!]/u', $display)) {
            return [];
        }

        $plain = self::asciiWords($display);

        if ($plain === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', $plain) ?: [];
        $found = [];
        $standaloneCodes = ['hr', 'en', 'fr', 'es', 'srp'];

        foreach ($tokens as $token) {
            if ($token === 'srpskohrvatski' || $token === 'hrvatskosrpski') {
                $found['Hrvatski'] = true;
                $found['Srpski'] = true;
                continue;
            }

            if (in_array($token, $standaloneCodes, true) && count($tokens) !== 1) {
                continue;
            }

            foreach (self::ORIGIN_LANGUAGES as $label => $aliases) {
                if (in_array($token, $aliases, true)) {
                    $found[$label] = true;
                }
            }
        }

        return array_values(array_filter(
            array_keys(self::ORIGIN_LANGUAGES),
            fn (string $label) => isset($found[$label])
        ));
    }

    private static function isSimpleOrigin(string $display, string $plain): bool
    {
        if ($plain === '' || preg_match('/[?!]/u', $display)) {
            return false;
        }

        // A script or another qualifier may carry useful information. Runtime
        // facets can ignore it, but persisted cleanup must keep the full text.
        $allowed = ['i', 'jezik', 'jezici'];
        $tokens = preg_split('/\s+/', $plain) ?: [];

        if (in_array($plain, ['sedmojezicno', 'devetojezicno'], true)) {
            return false;
        }

        if (count($tokens) > 1
            && array_intersect($tokens, ['hr', 'en', 'fr', 'es', 'srp'])) {
            return false;
        }

        foreach ($tokens as $token) {
            if (in_array($token, $allowed, true)
                || $token === 'srpskohrvatski'
                || $token === 'hrvatskosrpski') {
                continue;
            }

            $recognized = false;

            foreach (self::ORIGIN_LANGUAGES as $aliases) {
                if (in_array($token, $aliases, true)) {
                    $recognized = true;
                    break;
                }
            }

            if (! $recognized) {
                return false;
            }
        }

        return self::originFacetValues($display) !== [];
    }

    private static function asciiWords($value): string
    {
        $plain = Str::lower(Str::ascii(self::display($value)));
        $plain = preg_replace('/[^a-z0-9]+/', ' ', $plain) ?? '';

        return trim(preg_replace('/\s+/', ' ', $plain) ?? '');
    }

    private static function sentenceCase(string $value): string
    {
        $value = Str::lower($value);

        if ($value === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($value, 1, null, 'UTF-8');
    }

    private static function matchesCoreScriptToken(string $token, string $target): bool
    {
        if (strpos($token, $target) === 0) {
            return true;
        }

        if (abs(strlen($token) - strlen($target)) > 2 || levenshtein($token, $target) > 2) {
            return false;
        }

        $startsLikeTarget = isset($token[0]) && $token[0] === $target[0];
        $hasOneStrayPrefix = strlen($token) === strlen($target) + 1
            && isset($token[1])
            && $token[1] === $target[0];

        if (! $startsLikeTarget && ! $hasOneStrayPrefix) {
            return false;
        }

        // Short "gotica" has ordinary Croatian near-neighbours such as
        // "godina". Requiring its distinctive t prevents qualifier matches.
        return $target !== 'gotica' || strpos($token, 't') !== false;
    }

    /**
     * Strict key for destructive entity deduplication. Unlike the facet key,
     * this deliberately preserves diacritics so distinct names such as
     * "Sarić" and "Šarić" can never be merged automatically.
     */
    public static function entityKey($value): string
    {
        $value = Str::lower(self::display($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /**
     * A cautious person-name key that also recognizes initials written in a
     * different order, for example "C. G. Jung" and "G. Jung C.".
     */
    public static function personKey($value): string
    {
        $plain = Str::lower(self::display($value));
        $tokens = array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', $plain) ?: [],
            fn ($token) => $token !== '' && ! in_array($token, ['dr', 'mr', 'prof'], true)
        ));

        if (count($tokens) < 2) {
            return implode('', $tokens);
        }

        $surnameIndex = count($tokens) - 1;
        if (mb_strlen($tokens[$surnameIndex], 'UTF-8') === 1) {
            for ($index = $surnameIndex - 1; $index >= 0; $index--) {
                if (mb_strlen($tokens[$index], 'UTF-8') > 1) {
                    $surnameIndex = $index;
                    break;
                }
            }
        }

        $surname = $tokens[$surnameIndex];
        $initials = [];
        foreach ($tokens as $index => $token) {
            if ($index !== $surnameIndex) {
                $initials[] = mb_substr($token, 0, 1, 'UTF-8');
            }
        }
        sort($initials, SORT_STRING);

        return $surname . '|' . implode('', $initials);
    }

    /**
     * Group harmless variants of a person's name, including reversed full
     * names and initial-based forms that still share the same surname.
     *
     * @param iterable $entities Objects with a title property.
     */
    public static function groupPeople($entities): array
    {
        $groups = [];

        foreach ($entities as $entity) {
            $matchingGroups = [];
            foreach ($groups as $index => $group) {
                if ($group->contains(fn ($member) => self::personNamesMatch($entity->title, $member->title))) {
                    $matchingGroups[] = $index;
                }
            }

            if (empty($matchingGroups)) {
                $groups[] = collect([$entity]);
                continue;
            }

            $target = array_shift($matchingGroups);
            $groups[$target]->push($entity);
            foreach (array_reverse($matchingGroups) as $index) {
                $groups[$target] = $groups[$target]->merge($groups[$index]);
                array_splice($groups, $index, 1);
            }
        }

        return $groups;
    }

    public static function personNamesMatch($first, $second): bool
    {
        $firstDisplay = self::display($first);
        $secondDisplay = self::display($second);
        if ($firstDisplay === '' || $secondDisplay === '') {
            return false;
        }

        if (self::entityKey($firstDisplay) === self::entityKey($secondDisplay)) {
            return true;
        }

        $firstTokens = self::personNameTokens($firstDisplay);
        $secondTokens = self::personNameTokens($secondDisplay);
        if (empty($firstTokens) || empty($secondTokens)) {
            return false;
        }

        $firstSorted = $firstTokens;
        $secondSorted = $secondTokens;
        sort($firstSorted, SORT_STRING);
        sort($secondSorted, SORT_STRING);
        if ($firstSorted === $secondSorted) {
            return true;
        }

        $hasInitials = collect($firstTokens)->contains(fn ($token) => mb_strlen($token, 'UTF-8') === 1)
            || collect($secondTokens)->contains(fn ($token) => mb_strlen($token, 'UTF-8') === 1);
        if (! $hasInitials || count($firstTokens) !== count($secondTokens)) {
            return false;
        }

        $firstInitials = collect($firstTokens)
            ->map(fn ($token) => mb_substr($token, 0, 1, 'UTF-8'))
            ->sort()
            ->values();
        $secondInitials = collect($secondTokens)
            ->map(fn ($token) => mb_substr($token, 0, 1, 'UTF-8'))
            ->sort()
            ->values();
        $sharedFullTokens = array_intersect(
            array_filter($firstTokens, fn ($token) => mb_strlen($token, 'UTF-8') > 1),
            array_filter($secondTokens, fn ($token) => mb_strlen($token, 'UTF-8') > 1)
        );

        return $firstInitials->all() === $secondInitials->all() && ! empty($sharedFullTokens);
    }

    public static function personLabelScore($value): int
    {
        $label = self::display($value);
        $letters = preg_replace('/[^\pL\pN]+/u', '', $label) ?? '';
        $words = preg_split('/\s+/u', $label) ?: [];
        $uppercasePenalty = mb_strtoupper($label, 'UTF-8') === $label ? 20 : 0;

        return (count($words) * 100) + mb_strlen($letters, 'UTF-8') - $uppercasePenalty;
    }

    private static function personNameTokens($value): array
    {
        $plain = Str::lower(self::display($value));

        return array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', $plain) ?: [],
            fn ($token) => $token !== '' && ! in_array($token, ['dr', 'mr', 'prof'], true)
        ));
    }
}
