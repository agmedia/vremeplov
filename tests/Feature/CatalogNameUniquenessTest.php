<?php

namespace Tests\Feature;

use App\Http\Livewire\Back\Layout\Search\AuthorSearch;
use App\Http\Livewire\Back\Layout\Search\PublisherSearch;
use App\Models\Back\Catalog\Author;
use App\Models\Back\Catalog\Publisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogNameUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_author_entry_selects_existing_normalized_name(): void
    {
        $author = $this->author('Ivana Brlić Mažuranić');

        Livewire::test(AuthorSearch::class)
            ->set('new.title', '  IVANA   BRLIĆ MAŽURANIĆ  ')
            ->call('makeNewAuthor')
            ->assertSet('author_id', $author->id)
            ->assertSet('search', 'Ivana Brlić Mažuranić');

        $this->assertSame(1, Author::query()->count());
    }

    public function test_quick_publisher_entry_selects_existing_normalized_name(): void
    {
        $publisher = $this->publisher('Školska knjiga');

        Livewire::test(PublisherSearch::class)
            ->set('new.title', ' skolska   knjiga ')
            ->call('makeNewPublisher')
            ->assertSet('publisher_id', $publisher->id)
            ->assertSet('search', 'Školska knjiga');

        $this->assertSame(1, Publisher::query()->count());
    }

    public function test_regular_author_form_rejects_an_equivalent_name(): void
    {
        $this->author('August Šenoa');

        $this->expectException(ValidationException::class);

        (new Author())->validateRequest(new Request(['title' => ' august   senoa ']));
    }

    public function test_regular_publisher_form_rejects_an_equivalent_name(): void
    {
        $this->publisher('Znanje d.o.o.');

        $this->expectException(ValidationException::class);

        (new Publisher())->validateRequest(new Request(['title' => ' ZNANJE D.O.O. ']));
    }

    private function author(string $title): Author
    {
        return Author::query()->create([
            'letter' => mb_substr($title, 0, 1),
            'title' => $title,
            'description' => '',
            'meta_title' => $title,
            'meta_description' => '',
            'lang' => 'hr',
            'sort_order' => 0,
            'status' => 1,
            'slug' => \Illuminate\Support\Str::slug($title),
            'url' => 'autori/' . \Illuminate\Support\Str::slug($title),
        ]);
    }

    private function publisher(string $title): Publisher
    {
        return Publisher::query()->create([
            'letter' => mb_substr($title, 0, 1),
            'title' => $title,
            'description' => '',
            'meta_title' => $title,
            'meta_description' => '',
            'lang' => 'hr',
            'sort_order' => 0,
            'status' => 1,
            'slug' => \Illuminate\Support\Str::slug($title),
            'url' => 'izdavaci/' . \Illuminate\Support\Str::slug($title),
        ]);
    }
}
