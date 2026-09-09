<?php

namespace App\Console\Commands;

use App\Support\CatalogFilterValue;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class NormalizeCatalogFilterData extends Command
{
    private const PRODUCT_COLUMNS = [
        'letter',
        'condition',
        'binding',
        'origin',
    ];

    private const ENTITY_TABLES = [
        'authors' => [
            'label' => 'Autori',
            'foreign_key' => 'author_id',
            'action_group' => 'author',
            'protected_config' => 'settings.unknown_author',
        ],
        'publishers' => [
            'label' => 'Nakladnici',
            'foreign_key' => 'publisher_id',
            'action_group' => 'publisher',
            'protected_config' => 'settings.unknown_publisher',
        ],
    ];

    private const CHUNK_SIZE = 500;

    /**
     * The command is intentionally a dry-run unless --apply is supplied.
     *
     * @var string
     */
    protected $signature = 'catalog:normalize-filter-data
                            {--apply : Primijeni prikazane promjene u bazi}';

    /** @var string */
    protected $description = 'Sigurno normalizira filter vrijednosti i spaja samo konzervativne duplikate autora/nakladnika';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $this->line($apply
            ? 'Način rada: APPLY (promjene će biti zapisane transakcijski).'
            : 'Način rada: DRY-RUN (baza se neće mijenjati).');
        $this->newLine();

        try {
            $report = $apply
                ? DB::transaction(function () {
                    return $this->normalize(true);
                }, 3)
                : $this->normalize(false);
        } catch (Throwable $exception) {
            $this->error('Normalizacija nije primijenjena; transakcija je poništena.');
            $this->error($exception->getMessage());

            return 1;
        }

        $this->renderReport($report);

        if ($apply) {
            $this->info('Normalizacija je uspješno primijenjena.');
        } else {
            $this->warn('Dry-run je završen. Za zapisivanje istih vrsta promjena pokrenite naredbu s --apply.');
        }

        return 0;
    }

    private function normalize(bool $apply): array
    {
        $changedProductIds = [];
        $productChanges = [];
        $hasNoteColumn = Schema::hasColumn('products', 'note');
        $conditionNotesMoved = 0;
        $conditionNotesSkipped = 0;
        $manualReviews = [];

        foreach (self::PRODUCT_COLUMNS as $column) {
            $manualReviews[$column] = ['products' => 0, 'samples' => []];
        }

        foreach (self::PRODUCT_COLUMNS as $column) {
            $canonicalByKey = $this->canonicalProductValues($column);
            $productChanges[$column] = $this->normalizeProductColumn(
                $column,
                $canonicalByKey,
                $changedProductIds,
                $apply,
                $hasNoteColumn,
                $conditionNotesMoved,
                $conditionNotesSkipped,
                $manualReviews
            );
        }

        $entities = [];

        foreach (self::ENTITY_TABLES as $table => $definition) {
            $entities[$table] = $this->mergeEntityDuplicates(
                $table,
                $definition['foreign_key'],
                $definition['action_group'],
                (int) config($definition['protected_config'], 0),
                $apply
            );
        }

        return [
            'product_changes' => $productChanges,
            'changed_products' => count($changedProductIds),
            'has_note_column' => $hasNoteColumn,
            'condition_notes_moved' => $conditionNotesMoved,
            'condition_notes_skipped' => $conditionNotesSkipped,
            'manual_reviews' => $manualReviews,
            'entities' => $entities,
        ];
    }

    /**
     * Pick one already-observed cleaned label for each comparison key. The
     * most common variant wins; ties prefer human-readable mixed/title case.
     * No spelling correction or semantic mapping is performed here.
     */
    private function canonicalProductValues(string $column): array
    {
        $groups = [];

        DB::table('products')
            ->select(['id', $column])
            ->chunkById(self::CHUNK_SIZE, function ($products) use ($column, &$groups) {
                foreach ($products as $product) {
                    if ($product->{$column} === null) {
                        continue;
                    }

                    $display = CatalogFilterValue::storageDisplay($column, $product->{$column});
                    $key = CatalogFilterValue::storageKey($column, $display);

                    if ($display === '' || $key === '') {
                        continue;
                    }

                    if (! isset($groups[$key][$display])) {
                        $groups[$key][$display] = 0;
                    }

                    $groups[$key][$display]++;
                }
            }, 'id');

        $canonical = [];

        foreach ($groups as $key => $variants) {
            $labels = array_keys($variants);

            usort($labels, function (string $left, string $right) use ($variants) {
                $countComparison = $variants[$right] <=> $variants[$left];

                if ($countComparison !== 0) {
                    return $countComparison;
                }

                $caseComparison = $this->displayCaseScore($right) <=> $this->displayCaseScore($left);

                return $caseComparison !== 0 ? $caseComparison : strcmp($left, $right);
            });

            $canonical[$key] = $labels[0];
        }

        return $canonical;
    }

    private function displayCaseScore(string $value): int
    {
        $hasLowercase = preg_match('/\p{Ll}/u', $value) === 1;
        $hasUppercase = preg_match('/\p{Lu}/u', $value) === 1;

        if ($hasLowercase && $hasUppercase) {
            return 2;
        }

        return $hasUppercase ? 1 : 0;
    }

    private function normalizeProductColumn(
        string $column,
        array $canonicalByKey,
        array &$changedProductIds,
        bool $apply,
        bool $hasNoteColumn,
        int &$conditionNotesMoved,
        int &$conditionNotesSkipped,
        array &$manualReviews
    ): int {
        $changed = 0;
        $select = ['id', $column];

        if ($column === 'condition' && $hasNoteColumn) {
            $select[] = 'note';
        }

        DB::table('products')->select($select)->chunkById(
            self::CHUNK_SIZE,
            function ($products) use (
                $column,
                $canonicalByKey,
                &$changedProductIds,
                &$changed,
                $apply,
                $hasNoteColumn,
                &$conditionNotesMoved,
                &$conditionNotesSkipped,
                &$manualReviews
            ) {
                $updates = [];

                foreach ($products as $product) {
                    $current = $product->{$column};

                    if ($current === null) {
                        continue;
                    }

                    $currentNote = $column === 'condition' && $hasNoteColumn
                        ? $product->note
                        : null;
                    $conditionPlan = $this->conditionStoragePlan(
                        $column,
                        $current,
                        $currentNote,
                        $hasNoteColumn
                    );
                    $display = $conditionPlan['display'];
                    $key = CatalogFilterValue::storageKey($column, $display);
                    $normalized = $conditionPlan['force_canonical']
                        ? $display
                        : ($display === ''
                            ? null
                            : ($key !== '' && isset($canonicalByKey[$key]) ? $canonicalByKey[$key] : $display));
                    $noteChanged = $conditionPlan['note_changed'];

                    if ($conditionPlan['note_moved']) {
                        $conditionNotesMoved++;
                    }

                    if ($conditionPlan['note_skipped']) {
                        $conditionNotesSkipped++;
                    }

                    if ($this->needsManualReview(
                        $column,
                        $current,
                        $conditionPlan
                    )) {
                        $this->addManualReview($manualReviews[$column], $current);
                    }

                    $valueChanged = $normalized === null
                        ? $current !== null
                        : (string) $current !== $normalized;

                    if (! $valueChanged && ! $noteChanged) {
                        continue;
                    }

                    $changed++;
                    $changedProductIds[(int) $product->id] = true;

                    if (! $apply) {
                        continue;
                    }

                    $batchKey = hash('sha256', serialize([
                        (string) $current,
                        $normalized,
                        $noteChanged,
                        $conditionPlan['guard_note'],
                        $currentNote,
                        $conditionPlan['note'],
                    ]));

                    if (! isset($updates[$batchKey])) {
                        $updates[$batchKey] = [
                            'current' => (string) $current,
                            'value' => $normalized,
                            'note_changed' => $noteChanged,
                            'guard_note' => $conditionPlan['guard_note'],
                            'current_note' => $currentNote,
                            'note' => $conditionPlan['note'],
                            'ids' => [],
                        ];
                    }

                    $updates[$batchKey]['ids'][] = (int) $product->id;
                }

                foreach ($updates as $update) {
                    $query = DB::table('products')->whereIn('id', $update['ids']);
                    $this->whereExactValue($query, $column, $update['current']);
                    $values = [$column => $update['value']];

                    if ($update['guard_note']) {
                        $this->whereExactNullableValue($query, 'note', $update['current_note']);
                    }

                    if ($update['note_changed']) {
                        $values['note'] = $update['note'];
                    }

                    $updated = $query->update($values);

                    if ((int) $updated !== count($update['ids'])) {
                        throw new RuntimeException(sprintf(
                            'Proizvod je promijenjen tijekom normalizacije stupca %s; pokušajte ponovno.',
                            $column
                        ));
                    }
                }
            },
            'id'
        );

        return $changed;
    }

    private function needsManualReview(
        string $column,
        $current,
        array $conditionPlan
    ): bool {
        $display = CatalogFilterValue::display($current);

        if ($display === '') {
            return false;
        }

        $facetDisplay = CatalogFilterValue::facetDisplay($column, $current);

        if ($column === 'letter') {
            return $facetDisplay === '';
        }

        if ($facetDisplay === '') {
            return true;
        }

        if ($column === 'condition') {
            if ($conditionPlan['force_canonical']) {
                return false;
            }

            return CatalogFilterValue::storageDisplay($column, $current) !== $facetDisplay;
        }

        return CatalogFilterValue::storageDisplay($column, $current) !== $facetDisplay;
    }

    private function addManualReview(array &$review, $current): void
    {
        $review['products']++;

        if (count($review['samples']) >= 10) {
            return;
        }

        $sample = CatalogFilterValue::display($current);
        $sampleKey = CatalogFilterValue::entityKey($sample);

        foreach ($review['samples'] as $existing) {
            if (CatalogFilterValue::entityKey($existing) === $sampleKey) {
                return;
            }
        }

        $review['samples'][] = mb_substr($sample, 0, 120);
    }

    /**
     * A detailed but safely classified condition can be reduced to its
     * canonical filter value only after its complete cleaned text is retained
     * in the dedicated note column. Without that column (or enough room), the
     * legacy condition remains non-lossy for manual review.
     */
    private function conditionStoragePlan(
        string $column,
        $current,
        $currentNote,
        bool $hasNoteColumn
    ): array {
        $storageDisplay = CatalogFilterValue::storageDisplay($column, $current);
        $plan = [
            'display' => $storageDisplay,
            'force_canonical' => false,
            'note' => $currentNote,
            'note_changed' => false,
            'guard_note' => false,
            'note_moved' => false,
            'note_skipped' => false,
        ];

        if ($column !== 'condition' || ! $hasNoteColumn) {
            return $plan;
        }

        $canonical = CatalogFilterValue::facetDisplay('condition', $current);

        if ($canonical === '' || $storageDisplay === '' || $storageDisplay === $canonical) {
            return $plan;
        }

        $append = $this->appendNoteWithoutDuplicate($currentNote, $storageDisplay);

        if (! $append['fits']) {
            $plan['note_skipped'] = true;

            return $plan;
        }

        $plan['display'] = $canonical;
        $plan['force_canonical'] = true;
        $plan['guard_note'] = true;
        $plan['note'] = $append['note'];
        $plan['note_changed'] = $append['changed'];
        $plan['note_moved'] = $append['changed'];

        return $plan;
    }

    private function appendNoteWithoutDuplicate($currentNote, string $detail): array
    {
        $note = (string) $currentNote;
        $detailKey = CatalogFilterValue::entityKey($detail);

        foreach (preg_split('/\R+/u', $note) ?: [] as $line) {
            if ($detailKey !== '' && CatalogFilterValue::entityKey($line) === $detailKey) {
                return ['note' => $currentNote, 'changed' => false, 'fits' => true];
            }
        }

        if (trim($note) === '') {
            $appended = $detail;
        } else {
            $separator = preg_match('/\R\z/u', $note) ? '' : PHP_EOL;
            $appended = $note . $separator . $detail;
        }

        // The product form currently validates notes to 2,000 characters.
        // Keep the source condition untouched rather than creating a value the
        // administrator could no longer edit.
        if (mb_strlen($appended, 'UTF-8') > 2000) {
            return ['note' => $currentNote, 'changed' => false, 'fits' => false];
        }

        return ['note' => $appended, 'changed' => true, 'fits' => true];
    }

    private function whereExactValue(Builder $query, string $column, string $value): void
    {
        if ($query->getConnection()->getDriverName() === 'mysql') {
            $query->whereRaw('BINARY ' . $query->getGrammar()->wrap($column) . ' = ?', [$value]);

            return;
        }

        $query->where($column, $value);
    }

    private function whereExactNullableValue(Builder $query, string $column, $value): void
    {
        if ($value === null) {
            $query->whereNull($column);

            return;
        }

        $this->whereExactValue($query, $column, (string) $value);
    }

    private function mergeEntityDuplicates(
        string $table,
        string $foreignKey,
        string $actionGroup,
        int $protectedId,
        bool $apply
    ): array {
        $groups = [];
        $columns = Schema::getColumnListing($table);

        foreach (DB::table($table)->select('*')->orderBy('id')->get() as $entity) {
            $key = CatalogFilterValue::entityKey($entity->title);

            if ($key !== '') {
                $groups[$key][] = $entity;
            }
        }

        $report = [
            'groups' => 0,
            'duplicates' => 0,
            'products' => 0,
            'actions' => 0,
            'invalid_actions' => 0,
            'metadata_fields' => 0,
            'skipped_risky_groups' => 0,
            'skipped_risky_duplicates' => 0,
            'skipped_risky_products' => 0,
            'alternate_urls' => 0,
            'details' => [],
            'skipped_risky_details' => [],
        ];
        $plans = [];
        $idMap = [];

        foreach ($groups as $key => $entities) {
            if (count($entities) < 2) {
                continue;
            }

            $winner = $this->entityWinner($entities, $protectedId);
            $losers = array_values(array_filter($entities, function ($entity) use ($winner) {
                return (int) $entity->id !== (int) $winner->id;
            }));
            $loserIds = array_map(function ($entity) {
                return (int) $entity->id;
            }, $losers);
            $affectedProducts = DB::table('products')->whereIn($foreignKey, $loserIds)->count();
            $alternateUrls = $this->alternateEntityUrlCount($entities, $columns);

            if ($alternateUrls > 0) {
                $report['skipped_risky_groups']++;
                $report['skipped_risky_duplicates'] += count($loserIds);
                $report['skipped_risky_products'] += $affectedProducts;
                $report['alternate_urls'] += $alternateUrls;
                $report['skipped_risky_details'][] = [
                    'title' => (string) $winner->title,
                    'winner' => (int) $winner->id,
                    'losers' => $loserIds,
                    'products' => $affectedProducts,
                    'alternate_urls' => $alternateUrls,
                ];

                // A duplicate with another public slug/URL needs an explicit
                // redirect decision. It must never enter either the entity
                // merge plan or the product_actions ID map automatically.
                continue;
            }

            $report['groups']++;
            $report['duplicates'] += count($loserIds);
            $report['products'] += $affectedProducts;

            $report['details'][] = [
                'title' => (string) $winner->title,
                'winner' => (int) $winner->id,
                'losers' => $loserIds,
                'products' => $affectedProducts,
                'alternate_urls' => $alternateUrls,
            ];
            $plans[] = [
                'key' => $key,
                'entities' => $entities,
                'winner' => $winner,
                'loser_ids' => $loserIds,
            ];

            foreach ($loserIds as $loserId) {
                $idMap[$loserId] = (int) $winner->id;
            }
        }

        if ($apply && $plans) {
            $plans = $this->lockAndValidateEntityPlans($table, $plans, $protectedId, $columns);
        }

        $actionReport = $this->remapProductActionLinks($actionGroup, $idMap, $apply);
        $report['actions'] = $actionReport['changed'];
        $report['invalid_actions'] = $actionReport['invalid'];

        if (! $apply) {
            return $report;
        }

        foreach ($plans as $plan) {
            $entities = $plan['entities'];
            $winner = $plan['winner'];
            $loserIds = $plan['loser_ids'];
            $winnerUpdates = $this->entityWinnerUpdates($winner, $entities, $columns);

            if ($winnerUpdates) {
                DB::table($table)->where('id', $winner->id)->update($winnerUpdates);
                $report['metadata_fields'] += count($winnerUpdates);
            }

            DB::table('products')
                ->whereIn($foreignKey, $loserIds)
                ->update([$foreignKey => (int) $winner->id]);

            DB::table($table)->whereIn('id', $loserIds)->delete();
        }

        return $report;
    }

    private function alternateEntityUrlCount(array $entities, array $columns): int
    {
        $alternateCounts = [];

        foreach (['slug', 'url'] as $field) {
            if (! in_array($field, $columns, true)) {
                continue;
            }

            $values = [];

            foreach ($entities as $entity) {
                $value = trim((string) $entity->{$field});

                if ($value !== '') {
                    $values[$value] = true;
                }
            }

            $alternateCounts[] = max(0, count($values) - 1);
        }

        return $alternateCounts ? max($alternateCounts) : 0;
    }

    private function lockAndValidateEntityPlans(
        string $table,
        array $plans,
        int $protectedId,
        array $columns
    ): array {
        $ids = [];

        foreach ($plans as $plan) {
            foreach ($plan['entities'] as $entity) {
                $ids[] = (int) $entity->id;
            }
        }

        $locked = DB::table($table)
            ->whereIn('id', array_values(array_unique($ids)))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($plans as &$plan) {
            $entities = [];

            foreach ($plan['entities'] as $plannedEntity) {
                $entity = $locked->get((int) $plannedEntity->id);

                if (! $entity || CatalogFilterValue::entityKey($entity->title) !== $plan['key']) {
                    throw new RuntimeException(sprintf(
                        'Tablica %s promijenjena je tijekom normalizacije; pokušajte ponovno.',
                        $table
                    ));
                }

                $entities[] = $entity;
            }

            $winner = $this->entityWinner($entities, $protectedId);

            if ($this->alternateEntityUrlCount($entities, $columns) > 0) {
                throw new RuntimeException(sprintf(
                    'Slug ili URL u tablici %s promijenjen je tijekom normalizacije; ništa nije primijenjeno.',
                    $table
                ));
            }

            if ((int) $winner->id !== (int) $plan['winner']->id) {
                throw new RuntimeException(sprintf(
                    'Kanonski zapis u tablici %s promijenjen je tijekom normalizacije; pokušajte ponovno.',
                    $table
                ));
            }

            $plan['entities'] = $entities;
            $plan['winner'] = $winner;
        }
        unset($plan);

        return $plans;
    }

    private function entityWinnerUpdates($winner, array $entities, array $columns): array
    {
        $updates = [];

        foreach (['status', 'featured'] as $flag) {
            if (! in_array($flag, $columns, true)) {
                continue;
            }

            $value = max(array_map(function ($entity) use ($flag) {
                return (int) $entity->{$flag};
            }, $entities));

            if ((int) $winner->{$flag} !== $value) {
                $updates[$flag] = $value;
            }
        }

        foreach ([
            'letter',
            'description',
            'meta_title',
            'meta_description',
            'image',
            'lang',
            'slug',
            'url',
        ] as $field) {
            if (! in_array($field, $columns, true) || ! $this->emptyEntityMetadata($winner->{$field})) {
                continue;
            }

            foreach ($entities as $entity) {
                if ((int) $entity->id === (int) $winner->id || $this->emptyEntityMetadata($entity->{$field})) {
                    continue;
                }

                $updates[$field] = $entity->{$field};
                break;
            }
        }

        if (in_array('viewed', $columns, true)) {
            $views = min(4294967295, array_sum(array_map(function ($entity) {
                return max(0, (int) $entity->viewed);
            }, $entities)));

            if ((int) $winner->viewed !== $views) {
                $updates['viewed'] = $views;
            }
        }

        return $updates;
    }

    private function emptyEntityMetadata($value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    private function remapProductActionLinks(string $group, array $idMap, bool $apply): array
    {
        $report = ['changed' => 0, 'invalid' => 0];

        if (! $idMap || ! Schema::hasTable('product_actions')) {
            return $report;
        }

        $updates = [];
        $actions = DB::table('product_actions')
            ->select(['id', 'links'])
            ->where('group', $group)
            ->whereNotNull('links')
            ->get();

        foreach ($actions as $action) {
            $decoded = json_decode((string) $action->links, true);

            if (! is_array($decoded) || ! $this->isList($decoded)) {
                $report['invalid']++;
                continue;
            }

            $flattenedNestedList = false;
            $links = $this->flattenActionLinks($decoded, $flattenedNestedList);
            $normalized = [];
            $seen = [];
            $changed = false;
            $mappedSafeId = false;

            foreach ($links as $link) {
                $mapped = $link;
                $linkId = $this->actionLinkId($link);

                if ($linkId !== null && isset($idMap[$linkId])) {
                    $mapped = $idMap[$linkId];
                    $changed = true;
                    $mappedSafeId = true;
                }

                $mappedId = $this->actionLinkId($mapped);
                $uniqueKey = $mappedId !== null
                    ? 'id:' . $mappedId
                    : 'value:' . serialize($mapped);

                if (isset($seen[$uniqueKey])) {
                    $changed = true;
                    continue;
                }

                $seen[$uniqueKey] = true;
                $normalized[] = $mapped;
            }

            // Do not rewrite or merely tidy an action that does not reference
            // a safe merge plan. In particular, actions containing only a
            // skipped risky group must remain byte-for-byte untouched.
            if (! $mappedSafeId) {
                continue;
            }

            $changed = $changed || $flattenedNestedList;

            $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($json === false) {
                throw new RuntimeException('Nije moguće zapisati normalizirane poveznice marketinške akcije.');
            }

            $report['changed']++;
            $updates[] = [
                'id' => (int) $action->id,
                'current' => (string) $action->links,
                'value' => $json,
            ];
        }

        if ($apply && $report['invalid'] > 0) {
            throw new RuntimeException(sprintf(
                '%d marketinških akcija grupe %s nema ispravan JSON popis; ništa nije promijenjeno.',
                $report['invalid'],
                $group
            ));
        }

        if ($apply) {
            foreach ($updates as $update) {
                $query = DB::table('product_actions')->where('id', $update['id']);
                $this->whereExactValue($query, 'links', $update['current']);
                $updated = $query->update(['links' => $update['value']]);

                if ((int) $updated !== 1) {
                    throw new RuntimeException(
                        'Marketinška akcija promijenjena je tijekom normalizacije; pokušajte ponovno.'
                    );
                }
            }
        }

        return $report;
    }

    private function actionLinkId($value): ?int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }

        if (is_float($value) && is_finite($value) && $value >= 0 && floor($value) === $value) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^\s*\d+\s*$/D', $value)) {
            return (int) trim($value);
        }

        return null;
    }

    private function isList(array $values): bool
    {
        return $values === [] || array_keys($values) === range(0, count($values) - 1);
    }

    private function flattenActionLinks(array $values, bool &$nested): array
    {
        $flattened = [];

        foreach ($values as $value) {
            if (is_array($value)) {
                $nested = true;
                $flattened = array_merge($flattened, $this->flattenActionLinks($value, $nested));
            } else {
                $flattened[] = $value;
            }
        }

        return $flattened;
    }

    private function entityWinner(array $entities, int $protectedId)
    {
        foreach ($entities as $entity) {
            if ($protectedId > 0 && (int) $entity->id === $protectedId) {
                return $entity;
            }
        }

        usort($entities, function ($left, $right) {
            return (int) $left->id <=> (int) $right->id;
        });

        return $entities[0];
    }

    private function renderReport(array $report): void
    {
        $rows = [];

        foreach ($report['product_changes'] as $column => $count) {
            $rows[] = [$column, $count];
        }

        $this->line('Vrijednosti filtera na proizvodima:');
        $this->table(['Stupac', 'Promijenjene vrijednosti'], $rows);
        $this->line(sprintf('Proizvodi s barem jednom promjenom: %d.', $report['changed_products']));

        if ($report['has_note_column']) {
            $this->line(sprintf(
                'Detaljne vrijednosti stanja za dopunu napomene: %d.',
                $report['condition_notes_moved']
            ));

            if ($report['condition_notes_skipped'] > 0) {
                $this->warn(sprintf(
                    '  %d detaljnih vrijednosti ostaje za ručni pregled jer napomena nema dovoljno mjesta.',
                    $report['condition_notes_skipped']
                ));
            }
        } else {
            $this->line('Stupac note ne postoji; detaljne vrijednosti stanja ostaju nepromijenjene.');
        }

        foreach ($report['manual_reviews'] as $column => $review) {
            if ($review['products'] === 0) {
                continue;
            }

            $message = $column === 'letter'
                ? '%s: %d neprepoznatih vrijednosti bit će uklonjeno; provjerite uzorke prije --apply.'
                : '%s: %d vrijednosti nije kanonizirano i ostaje za ručni pregled.';
            $this->warn(sprintf($message, $column, $review['products']));

            foreach ($review['samples'] as $sample) {
                $this->line(sprintf('  - "%s"', $sample));
            }
        }

        $this->newLine();

        foreach (self::ENTITY_TABLES as $table => $definition) {
            $entity = $report['entities'][$table];
            $this->line(sprintf(
                '%s: %d sigurnih grupa, %d duplikata za uklanjanje, %d proizvoda i %d akcija za preusmjeravanje.',
                $definition['label'],
                $entity['groups'],
                $entity['duplicates'],
                $entity['products'],
                $entity['actions']
            ));

            if ($entity['invalid_actions'] > 0) {
                $this->warn(sprintf(
                    '  Upozorenje: %d akcija nema ispravan JSON popis; --apply će se sigurno prekinuti.',
                    $entity['invalid_actions']
                ));
            }

            if ($entity['skipped_risky_groups'] > 0) {
                $this->warn(sprintf(
                    '  Preskočeno zbog javnih slugova/URL-ova: %d grupa, %d duplikata i %d proizvoda; %d alternativnih adresa ostaje netaknuto.',
                    $entity['skipped_risky_groups'],
                    $entity['skipped_risky_duplicates'],
                    $entity['skipped_risky_products'],
                    $entity['alternate_urls']
                ));

                foreach (array_slice($entity['skipped_risky_details'], 0, 20) as $detail) {
                    $this->line(sprintf(
                        '  PRESKOČENO "%s": zapis #%d i duplikati #%s; proizvodi %d; alternativne adrese %d.',
                        mb_substr($detail['title'], 0, 80),
                        $detail['winner'],
                        implode(', #', $detail['losers']),
                        $detail['products'],
                        $detail['alternate_urls']
                    ));
                }

                if (count($entity['skipped_risky_details']) > 20) {
                    $this->line(sprintf(
                        '  ... i još %d preskočenih rizičnih grupa.',
                        count($entity['skipped_risky_details']) - 20
                    ));
                }
            }

            foreach (array_slice($entity['details'], 0, 20) as $detail) {
                $this->line(sprintf(
                    '  "%s": zadržava se #%d; duplikati #%s; proizvodi %d.',
                    mb_substr($detail['title'], 0, 80),
                    $detail['winner'],
                    implode(', #', $detail['losers']),
                    $detail['products']
                ));
            }

            if (count($entity['details']) > 20) {
                $this->line(sprintf('  ... i još %d grupa.', count($entity['details']) - 20));
            }
        }

        $this->newLine();
    }
}
