<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BlogRelatedProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['settings.images_domain' => 'https://cdn.example.test/']);

        // The imported production schema has this legacy column, while the
        // original products migration predates it.
        if (! Schema::hasColumn('products', 'group')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('group')->nullable();
            });
        }
    }

    public function test_author_slider_renders_only_available_active_books_by_the_selected_author(): void
    {
        $selectedAuthor = $this->createAuthor('Odabrani autor');
        $otherAuthor = $this->createAuthor('Drugi autor');
        $blogSlug = $this->createBlog([
            'mode' => 'author',
            'title' => 'Još knjiga odabranog autora',
            'author_id' => $selectedAuthor,
        ]);

        $this->createProduct('Dostupna knjiga autora', $selectedAuthor, 'knjige', true, 2, 100);
        $this->createProduct('Rasprodana knjiga autora', $selectedAuthor, 'knjige', true, 0, 500);
        $this->createProduct('Neaktivna knjiga autora', $selectedAuthor, 'knjige', false, 3, 400);
        $this->createProduct('Karta istog autora', $selectedAuthor, 'zemljopisne-karte', true, 4, 600);
        $this->createProduct('Knjiga drugog autora', $otherAuthor, 'knjige', true, 5, 700);

        $response = $this->get(route('catalog.route.blog', ['blog' => $blogSlug]));

        $response->assertOk();
        $response->assertSee('widget-product-carousel', false);
        $response->assertSee('Još knjiga odabranog autora');
        $response->assertSee('Dostupna knjiga autora');
        $response->assertDontSee('Rasprodana knjiga autora');
        $response->assertDontSee('Neaktivna knjiga autora');
        $response->assertDontSee('Karta istog autora');
        $response->assertDontSee('Knjiga drugog autora');
    }

    public function test_manual_slider_preserves_selection_order_and_filters_unavailable_or_non_book_items(): void
    {
        $author = $this->createAuthor('Autor ručnog odabira');
        $first = $this->createProduct('Prva prikazana knjiga', $author, 'knjige', true, 1, 10);
        $second = $this->createProduct('Druga prikazana knjiga', $author, 'knjige', true, 1, 900);
        $soldOut = $this->createProduct('Rasprodana ručno odabrana knjiga', $author, 'knjige', true, 0, 800);
        $inactive = $this->createProduct('Neaktivna ručno odabrana knjiga', $author, 'knjige', false, 1, 700);
        $nonBook = $this->createProduct('Ručno odabrana karta', $author, 'zemljopisne-karte', true, 1, 1000);

        $blogSlug = $this->createBlog([
            'mode' => 'books',
            'title' => 'Odabrano uz članak',
            'product_ids' => [$second, $soldOut, $nonBook, $first, $inactive],
        ]);

        $response = $this->get(route('catalog.route.blog', ['blog' => $blogSlug]));

        $response->assertOk();
        $response->assertSee('Odabrano uz članak');
        $response->assertSeeInOrder([
            'Druga prikazana knjiga',
            'Prva prikazana knjiga',
        ]);
        $response->assertDontSee('Rasprodana ručno odabrana knjiga');
        $response->assertDontSee('Neaktivna ručno odabrana knjiga');
        $response->assertDontSee('Ručno odabrana karta');
    }

    public function test_blog_article_does_not_repeat_a_summary_that_already_starts_the_body(): void
    {
        $summary = 'Isti uvodni tekst spremljen je i kao sažetak i kao prvi odlomak.';
        $slug = $this->createBlogPage('Članak bez ponovljenog uvoda', $summary, '<p>' . $summary . '</p><p>Nastavak članka.</p>');

        $response = $this->get(route('catalog.route.blog', ['blog' => $slug]));

        $response->assertOk();
        $response->assertDontSee('<p class="blog-article__lead">', false);
        $response->assertSee($summary);
    }

    public function test_blog_admin_lists_newest_posts_first(): void
    {
        $this->createBlogPage(
            'Stariji članak',
            'Stariji sažetak.',
            '<p>Stariji sadržaj.</p>',
            now()->subDay()
        );
        $this->createBlogPage(
            'Najnoviji članak',
            'Noviji sažetak.',
            '<p>Noviji sadržaj.</p>',
            now()
        );

        $response = $this->actingAs(User::factory()->create())->get(route('blogs'));

        $response->assertOk();
        $response->assertSeeInOrder(['Najnoviji članak', 'Stariji članak']);
    }

    public function test_blog_admin_requires_an_author_for_author_mode(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->from(route('blogs.create'))
            ->post(route('blogs.store'), [
                'title' => 'Članak bez odabranog autora',
                'related_slider_mode' => 'author',
                'related_slider_author_id' => '',
            ]);

        $response->assertRedirect(route('blogs.create'));
        $response->assertSessionHasErrors('related_slider_author_id');
        $this->assertDatabaseMissing('pages', ['title' => 'Članak bez odabranog autora']);
    }

    public function test_blog_admin_rejects_non_book_items_in_manual_mode(): void
    {
        $author = $this->createAuthor('Autor karte');
        $map = $this->createProduct('Karta nije knjiga', $author, 'zemljopisne-karte', true, 1, 10);

        $response = $this->actingAs(User::factory()->create())
            ->from(route('blogs.create'))
            ->post(route('blogs.store'), [
                'title' => 'Članak s pogrešnim artiklom',
                'related_slider_mode' => 'books',
                'related_slider_products' => [$map],
            ]);

        $response->assertRedirect(route('blogs.create'));
        $response->assertSessionHasErrors('related_slider_products.0');
        $this->assertDatabaseMissing('pages', ['title' => 'Članak s pogrešnim artiklom']);
    }

    private function createBlog(array $relatedSlider): string
    {
        $slug = 'testni-blog-' . Str::random(8);

        DB::table('pages')->insert([
            'group' => 'blog',
            'title' => 'Testni blog članak',
            'slug' => $slug,
            'short_description' => 'Sažetak testnog članka.',
            'description' => '<h2>Sadržaj članka</h2><p>Tekst članka.</p>',
            'image' => 'media/img/blog/test.jpg',
            'related_slider' => json_encode($relatedSlider),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $slug;
    }

    private function createBlogPage(string $title, string $summary, string $description, $createdAt = null): string
    {
        $slug = Str::slug($title) . '-' . Str::random(8);
        $createdAt = $createdAt ?: now();

        DB::table('pages')->insert([
            'group' => 'blog',
            'title' => $title,
            'slug' => $slug,
            'short_description' => $summary,
            'description' => $description,
            'image' => 'media/img/blog/test.jpg',
            'status' => true,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $slug;
    }

    private function createAuthor(string $title): int
    {
        $slug = Str::slug($title);

        return DB::table('authors')->insertGetId([
            'letter' => mb_substr($title, 0, 1),
            'title' => $title,
            'slug' => $slug,
            'url' => 'autor/' . $slug,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProduct(
        string $name,
        int $authorId,
        string $group,
        bool $active,
        int $quantity,
        int $viewed
    ): int {
        $slug = Str::slug($name);

        return DB::table('products')->insertGetId([
            'author_id' => $authorId,
            'name' => $name,
            'sku' => 'T' . Str::random(8),
            'group' => $group,
            'slug' => $slug,
            'url' => $group . '/' . $slug,
            'image' => 'media/img/products/test.jpg',
            'price' => 10,
            'quantity' => $quantity,
            'viewed' => $viewed,
            'status' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
