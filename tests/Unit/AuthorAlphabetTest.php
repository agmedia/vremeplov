<?php

namespace Tests\Unit;

use App\Helpers\Helper;
use App\Http\Controllers\Front\CatalogRouteController;
use App\Models\Front\Catalog\Author;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthorAlphabetTest extends TestCase
{
    public function test_croatian_letters_use_the_correct_order(): void
    {
        $letters = Helper::abc();

        $this->assertSame([
            'A', 'B', 'C', 'Č', 'Ć', 'D', 'Dž', 'Đ', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'Lj',
            'M', 'N', 'Nj', 'O', 'P', 'R', 'S', 'Š', 'T', 'U', 'V', 'Z', 'Ž',
        ], array_slice($letters, 0, 30));

        $this->assertSame(['Q', 'W', 'X', 'Y'], array_slice($letters, 30));
    }

    public function test_letter_filter_normalizes_case_and_rejects_unknown_values(): void
    {
        $this->assertSame('Č', Helper::resolveLetter(Request::create('/autor', 'GET', ['letter' => 'č'])));
        $this->assertSame('Dž', Helper::resolveLetter(Request::create('/autor', 'GET', ['letter' => 'DŽ'])));
        $this->assertSame(0, Helper::resolveLetter(Request::create('/autor', 'GET', ['letter' => '@'])));
    }

    public function test_author_filter_distinguishes_diacritics_but_accepts_legacy_lowercase_letters(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->string('slug');
            $table->string('url');
            $table->string('letter', 2);
            $table->boolean('status')->default(true);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('author_id');
            $table->boolean('status')->default(true);
            $table->decimal('price', 10, 2)->default(10);
            $table->integer('quantity')->default(1);
        });

        foreach (['C', 'c', 'Č', 'č', 'Ć', 'ć'] as $index => $letter) {
            $authorId = DB::table('authors')->insertGetId([
                'title' => $letter . ' autor',
                'slug' => 'autor-' . $index,
                'url' => 'autor/autor-' . $index,
                'letter' => $letter,
                'status' => true,
            ]);

            DB::table('products')->insert([
                'author_id' => $authorId,
                'status' => true,
                'price' => 10,
                'quantity' => 1,
            ]);
        }

        Cache::flush();
        $this->app->instance('request', Request::create('/autor', 'GET'));

        $titles = Author::getByLetter('Č')->pluck('title')->all();

        $this->assertEqualsCanonicalizing(['Č autor', 'č autor'], $titles);
        $this->assertTrue(
            Author::getLetters()->firstWhere('value', 'Č')['active']
        );

        foreach (['Carli', 'Čarli', 'Ćarli'] as $index => $title) {
            $authorId = DB::table('authors')->insertGetId([
                'title' => $title,
                'slug' => 'suggest-' . $index,
                'url' => 'autor/suggest-' . $index,
                'letter' => mb_substr($title, 0, 1, 'UTF-8'),
                'status' => true,
            ]);

            DB::table('products')->insert([
                'author_id' => $authorId,
                'status' => true,
                'price' => 10,
                'quantity' => 1,
            ]);
        }

        $response = (new CatalogRouteController())->authorSuggest(
            Request::create('/autor/suggest', 'GET', ['q' => 'ča'])
        );
        $suggestions = collect($response->getData(true)['authors'])->pluck('title')->all();

        $this->assertSame(['Čarli'], $suggestions);
    }
}
