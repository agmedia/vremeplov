<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NormalizeCatalogFilterDataCommandTest extends TestCase
{
    /** @var string */
    private $originalConnection;

    /** @var mixed */
    private $originalUnknownAuthor;

    /** @var mixed */
    private $originalUnknownPublisher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        $this->originalUnknownAuthor = config('settings.unknown_author');
        $this->originalUnknownPublisher = config('settings.unknown_publisher');

        config([
            'database.default' => 'catalog_normalization_testing',
            'database.connections.catalog_normalization_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'settings.unknown_author' => 999,
            'settings.unknown_publisher' => 999,
        ]);

        DB::purge('catalog_normalization_testing');
        $this->createSchema();
        $this->seedCatalog();
    }

    protected function tearDown(): void
    {
        DB::purge('catalog_normalization_testing');
        config([
            'database.default' => $this->originalConnection,
            'settings.unknown_author' => $this->originalUnknownAuthor,
            'settings.unknown_publisher' => $this->originalUnknownPublisher,
        ]);

        parent::tearDown();
    }

    public function test_command_is_a_read_only_dry_run_by_default(): void
    {
        $before = $this->snapshot();

        $this->assertSame(0, Artisan::call('catalog:normalize-filter-data'));
        $output = Artisan::output();

        $this->assertStringContainsString('DRY-RUN', $output);
        $this->assertStringContainsString('baza se neće mijenjati', $output);
        $this->assertStringContainsString('Detaljne vrijednosti stanja za dopunu napomene: 1.', $output);
        $this->assertStringContainsString(
            'condition: 1 vrijednosti nije kanonizirano',
            $output
        );
        $this->assertStringContainsString('Autori: 2 sigurnih grupa, 2 duplikata', $output);
        $this->assertStringContainsString('Nakladnici: 1 sigurnih grupa, 1 duplikata', $output);
        $this->assertStringContainsString(
            'Preskočeno zbog javnih slugova/URL-ova: 1 grupa, 1 duplikata',
            $output
        );
        $this->assertSame($before, $this->snapshot());
    }

    public function test_apply_normalizes_values_merges_only_conservative_duplicates_and_is_idempotent(): void
    {
        $this->assertSame(0, Artisan::call('catalog:normalize-filter-data', ['--apply' => true]));

        $products = DB::table('products')->orderBy('id')->get()->keyBy('id');

        $this->assertSame('Latinica', $products[1]->letter);
        $this->assertSame('Vrlo dobro', $products[1]->condition);
        $this->assertSame('Tvrdi', $products[1]->binding);
        $this->assertSame('Hrvatski', $products[1]->origin);
        $this->assertSame('Latinica', $products[3]->letter);
        $this->assertNull($products[4]->letter);
        $this->assertSame('108', $products[4]->origin);
        $this->assertSame('Dobro', $products[5]->condition);
        $this->assertSame(
            "Oštećenje korica\nDOBRO, s posvetom na prvoj stranici",
            $products[5]->note
        );
        $this->assertSame('MEKI, PREUKORIČENO', $products[5]->binding);
        $this->assertSame('Hrvatski, latinica', $products[5]->origin);
        $this->assertSame('1. svezak', $products[6]->condition);
        $this->assertSame(
            'Latinica',
            $products[6]->letter,
            'Independent facet cleanup still applies while the risky entity references stay unchanged.'
        );

        // Full name and initials remain separate; only variants inside each
        // conservative punctuation/whitespace/case group are merged.
        $this->assertSame(
            [1, 3, 5, 6, 7, 8, 9],
            DB::table('authors')->orderBy('id')->pluck('id')->all()
        );
        $this->assertSame(1, (int) DB::table('authors')->where('id', 1)->value('status'));
        $this->assertSame(1, (int) DB::table('authors')->where('id', 1)->value('featured'));
        $this->assertSame(1, (int) $products[1]->author_id);
        $this->assertSame(3, (int) $products[2]->author_id);
        $this->assertSame(5, (int) $products[3]->author_id);
        $this->assertSame(9, (int) $products[6]->author_id);
        $this->assertSame(
            2,
            DB::table('authors')->whereIn('title', ['Ivan Sarić', 'Ivan Šarić'])->count(),
            'Persisted entity merge must preserve diacritics.'
        );

        $this->assertSame(
            [1, 3, 4, 5],
            DB::table('publishers')->orderBy('id')->pluck('id')->all()
        );
        $this->assertSame(1, (int) $products[1]->publisher_id);
        $this->assertSame(5, (int) $products[6]->publisher_id);
        $this->assertSame(
            [1, 3, 8, 9],
            json_decode(DB::table('product_actions')->where('id', 1)->value('links'), true)
        );
        $this->assertSame(
            [1, 3, 4, 5],
            json_decode(DB::table('product_actions')->where('id', 2)->value('links'), true)
        );
        $this->assertSame(
            '[8,8,9]',
            DB::table('product_actions')->where('id', 4)->value('links'),
            'An action containing only a skipped risky group must remain byte-for-byte unchanged.'
        );
        $this->assertSame(1, (int) DB::table('authors')->where('id', 8)->value('status'));
        $this->assertSame(0, (int) DB::table('authors')->where('id', 9)->value('status'));
        $this->assertSame(1, (int) DB::table('publishers')->where('id', 4)->value('status'));
        $this->assertSame(0, (int) DB::table('publishers')->where('id', 5)->value('status'));

        $afterFirstApply = $this->snapshot();

        $this->assertSame(0, Artisan::call('catalog:normalize-filter-data', ['--apply' => true]));
        $secondOutput = Artisan::output();

        $this->assertStringContainsString('Proizvodi s barem jednom promjenom: 0.', $secondOutput);
        $this->assertStringContainsString('Autori: 0 sigurnih grupa, 0 duplikata', $secondOutput);
        $this->assertStringContainsString('Nakladnici: 0 sigurnih grupa, 0 duplikata', $secondOutput);
        $this->assertStringContainsString(
            'Preskočeno zbog javnih slugova/URL-ova: 1 grupa, 1 duplikata',
            $secondOutput
        );
        $this->assertSame($afterFirstApply, $this->snapshot());
    }

    public function test_numeric_origin_is_kept_as_a_string_and_is_not_planned_as_a_change(): void
    {
        DB::table('product_actions')->delete();
        DB::table('products')->where('id', '!=', 4)->delete();
        DB::table('products')->where('id', 4)->update([
            'letter' => null,
            'condition' => null,
            'binding' => null,
            'origin' => '108',
        ]);
        DB::table('authors')->where('id', '!=', 1)->delete();
        DB::table('publishers')->where('id', '!=', 1)->delete();

        $this->assertSame(0, Artisan::call('catalog:normalize-filter-data'));
        $this->assertStringContainsString(
            'Proizvodi s barem jednom promjenom: 0.',
            Artisan::output()
        );

        $this->assertSame(0, Artisan::call('catalog:normalize-filter-data', ['--apply' => true]));
        $this->assertSame('108', DB::table('products')->where('id', 4)->value('origin'));
    }

    public function test_failed_entity_merge_rolls_back_the_entire_apply(): void
    {
        $before = $this->snapshot();

        DB::statement("CREATE TRIGGER prevent_publisher_delete
            BEFORE DELETE ON publishers
            BEGIN
                SELECT RAISE(ABORT, 'blocked for test');
            END");

        $this->assertSame(1, Artisan::call('catalog:normalize-filter-data', ['--apply' => true]));

        $this->assertStringContainsString('transakcija je poništena', Artisan::output());
        $this->assertSame($before, $this->snapshot());
    }

    private function createSchema(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('url')->nullable();
            $table->boolean('status')->default(false);
            $table->boolean('featured')->default(false);
        });

        Schema::create('publishers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('url')->nullable();
            $table->boolean('status')->default(false);
            $table->boolean('featured')->default(false);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('author_id')->default(0);
            $table->unsignedBigInteger('publisher_id')->default(0);
            $table->string('letter')->nullable();
            $table->string('condition')->nullable();
            $table->string('binding')->nullable();
            $table->text('note')->nullable();
            $table->string('origin')->nullable();
        });

        Schema::create('product_actions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('group');
            $table->text('links')->nullable();
        });
    }

    private function seedCatalog(): void
    {
        DB::table('authors')->insert([
            ['id' => 1, 'title' => 'Carl Gustav Jung', 'slug' => 'carl-gustav-jung', 'url' => 'autori/carl-gustav-jung', 'status' => 0, 'featured' => 0],
            ['id' => 2, 'title' => ' carl   gustav jung, ', 'slug' => 'carl-gustav-jung', 'url' => 'autori/carl-gustav-jung', 'status' => 1, 'featured' => 1],
            ['id' => 3, 'title' => 'C. G. Jung', 'slug' => 'c-g-jung', 'url' => 'autori/c-g-jung', 'status' => 1, 'featured' => 0],
            ['id' => 4, 'title' => 'c.g.jung;', 'slug' => 'c-g-jung', 'url' => 'autori/c-g-jung', 'status' => 0, 'featured' => 0],
            ['id' => 5, 'title' => 'Chang Jung', 'slug' => 'chang-jung', 'url' => 'autori/chang-jung', 'status' => 1, 'featured' => 0],
            ['id' => 6, 'title' => 'Ivan Sarić', 'slug' => 'ivan-saric', 'url' => 'autori/ivan-saric', 'status' => 1, 'featured' => 0],
            ['id' => 7, 'title' => 'Ivan Šarić', 'slug' => 'ivan-saric-2', 'url' => 'autori/ivan-saric-2', 'status' => 1, 'featured' => 0],
            ['id' => 8, 'title' => 'Rizični Autor', 'slug' => 'rizicni-autor', 'url' => 'autori/rizicni-autor', 'status' => 1, 'featured' => 0],
            ['id' => 9, 'title' => ' rizični   autor, ', 'slug' => 'stari-rizicni-autor', 'url' => 'autori/stari-rizicni-autor', 'status' => 0, 'featured' => 0],
        ]);

        DB::table('publishers')->insert([
            ['id' => 1, 'title' => 'Znanje d.o.o.', 'slug' => 'znanje-d-o-o', 'url' => 'nakladnici/znanje-d-o-o', 'status' => 0, 'featured' => 0],
            ['id' => 2, 'title' => ' ZNANJE D.O.O. ', 'slug' => 'znanje-d-o-o', 'url' => 'nakladnici/znanje-d-o-o', 'status' => 1, 'featured' => 0],
            ['id' => 3, 'title' => 'Znanje', 'slug' => 'znanje', 'url' => 'nakladnici/znanje', 'status' => 1, 'featured' => 0],
            ['id' => 4, 'title' => 'Rizični nakladnik', 'slug' => 'rizicni-nakladnik', 'url' => 'nakladnici/rizicni-nakladnik', 'status' => 1, 'featured' => 0],
            ['id' => 5, 'title' => ' RIZIČNI NAKLADNIK ', 'slug' => 'stari-rizicni-nakladnik', 'url' => 'nakladnici/stari-rizicni-nakladnik', 'status' => 0, 'featured' => 0],
        ]);

        DB::table('products')->insert([
            [
                'id' => 1,
                'author_id' => 2,
                'publisher_id' => 2,
                'letter' => '&amp;lt;span&amp;gt;LATINICA,&amp;lt;/span&amp;gt;',
                'condition' => '<span>Vrlo&nbsp;&nbsp;dobro,</span>',
                'binding' => '&nbsp; TVRDI, &nbsp;',
                'note' => null,
                'origin' => '&amp;lt;span&amp;gt;HRVATSKI,&amp;lt;/span&amp;gt;',
            ],
            [
                'id' => 2,
                'author_id' => 4,
                'publisher_id' => 1,
                'letter' => 'LATINICA',
                'condition' => 'Vrlo dobro',
                'binding' => 'TVRDI',
                'note' => null,
                'origin' => 'HRVATSKI',
            ],
            [
                'id' => 3,
                'author_id' => 5,
                'publisher_id' => 3,
                'letter' => 'latinica',
                'condition' => 'vrlo dobro',
                'binding' => 'tvrdi',
                'note' => null,
                'origin' => 'hrvatski',
            ],
            [
                'id' => 4,
                'author_id' => 1,
                'publisher_id' => 1,
                'letter' => ' ,.; ',
                'condition' => null,
                'binding' => null,
                'note' => null,
                'origin' => '108',
            ],
            [
                'id' => 5,
                'author_id' => 1,
                'publisher_id' => 1,
                'letter' => 'ĆIRIKICA',
                'condition' => '<span>DOBRO, s posvetom na prvoj stranici.</span>',
                'binding' => 'MEKI, PREUKORIČENO',
                'note' => 'Oštećenje korica',
                'origin' => 'Hrvatski, latinica',
            ],
            [
                'id' => 6,
                'author_id' => 9,
                'publisher_id' => 5,
                'letter' => 'LATIBICA',
                'condition' => '1. svezak',
                'binding' => null,
                'note' => null,
                'origin' => null,
            ],
        ]);

        DB::table('product_actions')->insert([
            ['id' => 1, 'group' => 'author', 'links' => '[2,3,4,8,9]'],
            ['id' => 2, 'group' => 'publisher', 'links' => '[2,3,4,5]'],
            ['id' => 3, 'group' => 'product', 'links' => '[2,3,4]'],
            ['id' => 4, 'group' => 'author', 'links' => '[8,8,9]'],
        ]);
    }

    private function snapshot(): array
    {
        return [
            'authors' => DB::table('authors')->orderBy('id')->get()->map(function ($row) {
                return (array) $row;
            })->all(),
            'publishers' => DB::table('publishers')->orderBy('id')->get()->map(function ($row) {
                return (array) $row;
            })->all(),
            'products' => DB::table('products')->orderBy('id')->get()->map(function ($row) {
                return (array) $row;
            })->all(),
            'product_actions' => DB::table('product_actions')->orderBy('id')->get()->map(function ($row) {
                return (array) $row;
            })->all(),
        ];
    }
}
