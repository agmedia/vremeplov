<?php

namespace Tests\Feature;

use App\Helpers\Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomepageBlogWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['settings.images_domain' => 'https://cdn.example.test/']);
    }

    public function test_new_blog_widget_ignores_manual_selection_and_renders_at_most_five_latest_active_posts(): void
    {
        $groupId = $this->createBlogWidgetGroup('najnovije-s-bloga');
        $baseTime = now()->startOfMinute();
        $activeBlogIds = [];

        for ($position = 1; $position <= 6; $position++) {
            $activeBlogIds[$position] = $this->createBlog(
                'Aktivni blog ' . $position,
                $baseTime->copy()->subHours($position),
                true
            );
        }

        $this->createBlog('Neaktivni najnoviji blog', $baseTime->copy()->addHour(), false);

        $this->createBlogWidget($groupId, [
            'target' => 'blog',
            'new' => 'on',
            'list' => [$activeBlogIds[6]],
        ]);

        $rendered = Helper::setDescription('++najnovije-s-bloga++');

        $this->assertSame(5, substr_count($rendered, 'class="card h-100"'));

        for ($position = 1; $position <= 5; $position++) {
            $this->assertStringContainsString('Aktivni blog ' . $position, $rendered);
        }

        $this->assertStringNotContainsString('Aktivni blog 6', $rendered);
        $this->assertStringNotContainsString('Neaktivni najnoviji blog', $rendered);
        $this->assertLessThan(
            strpos($rendered, 'Aktivni blog 5'),
            strpos($rendered, 'Aktivni blog 1')
        );
    }

    public function test_manual_blog_selection_still_applies_when_new_mode_is_off(): void
    {
        $groupId = $this->createBlogWidgetGroup('rucni-izbor-blogova');
        $baseTime = now()->startOfMinute();
        $firstSelectedId = $this->createBlog('Prvi odabrani blog', $baseTime->copy()->subHours(3), true);
        $this->createBlog('Blog koji nije odabran', $baseTime->copy()->subHour(), true);
        $secondSelectedId = $this->createBlog('Drugi odabrani blog', $baseTime->copy()->subHours(2), true);

        $this->createBlogWidget($groupId, [
            'target' => 'blog',
            'list' => [$firstSelectedId, $secondSelectedId],
        ]);

        $rendered = Helper::setDescription('++rucni-izbor-blogova++');

        $this->assertSame(2, substr_count($rendered, 'class="card h-100"'));
        $this->assertStringContainsString('Prvi odabrani blog', $rendered);
        $this->assertStringContainsString('Drugi odabrani blog', $rendered);
        $this->assertStringNotContainsString('Blog koji nije odabran', $rendered);
    }

    private function createBlogWidgetGroup(string $slug): int
    {
        return DB::table('widget_groups')->insertGetId([
            'template' => 'page_carousel',
            'title' => 'Blog',
            'slug' => $slug,
            'width' => 12,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createBlogWidget(int $groupId, array $data): void
    {
        DB::table('widgets')->insert([
            'group_id' => $groupId,
            'title' => 'Novosti i objave',
            'subtitle' => 'Najnovije s bloga.',
            'data' => serialize($data),
            'url' => '/blog',
            'width' => 12,
            'sort_order' => 1,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createBlog(string $title, $createdAt, bool $active): int
    {
        return DB::table('pages')->insertGetId([
            'group' => 'blog',
            'title' => $title,
            'short_description' => 'Sažetak za ' . $title,
            'description' => '<p>Sadržaj.</p>',
            'slug' => str_replace(' ', '-', strtolower($title)),
            'status' => $active,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
