<?php

namespace Tests\Feature;

use App\Helpers\Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomepageWidgetRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['settings.images_domain' => 'https://cdn.example.test/']);
    }

    public function test_editor_wrapped_widget_tokens_render_in_page_order_with_the_local_square_image(): void
    {
        $secondGroup = $this->createCustomWidgetGroup('second-widget', 'Drugi widget');
        $firstGroup = $this->createCustomWidgetGroup('first-widget', 'Prvi widget');

        $this->createCustomWidget($secondGroup, 'DRUGI WIDGET', null);
        $this->createCustomWidget(
            $firstGroup,
            'PRVI WIDGET',
            'media/img/boxnow-banner-mobile.jpg'
        );

        $rendered = Helper::setDescription(
            '<p class="editor-widget">&nbsp;++first-widget++<br></p>'
            . '<p>++second-widget++</p>'
        );

        $this->assertStringNotContainsString('++first-widget++', $rendered);
        $this->assertStringNotContainsString('++second-widget++', $rendered);
        $this->assertDoesNotMatchRegularExpression('~<p\b[^>]*>\s*<section~i', $rendered);
        $this->assertLessThan(
            strpos($rendered, 'DRUGI WIDGET'),
            strpos($rendered, 'PRVI WIDGET')
        );

        $localImageUrl = asset('media/img/boxnow-banner-mobile.jpg');

        $this->assertStringContainsString('src="' . $localImageUrl . '"', $rendered);
        $this->assertStringContainsString(
            'widget-touch-carousel widget-custom-hero-carousel shadow',
            $rendered
        );
        $this->assertStringNotContainsString(
            'https://cdn.example.test/media/img/boxnow-banner-mobile.jpg',
            $rendered
        );
        $this->assertStringContainsString('width="800" height="800"', $rendered);
        $this->assertStringContainsString('Besplatna dostava do 1.10.', $rendered);
        $this->assertStringContainsString('Preuzimanje 24/7', $rendered);
        $this->assertStringContainsString('fa-clock', $rendered);
    }

    public function test_homepage_hides_the_seo_intro_visually_and_does_not_render_the_old_hardcoded_banner(): void
    {
        DB::table('pages')->insert([
            'title' => 'Naslovnica',
            'slug' => 'homepage',
            'description' => '<p>Sadržaj naslovnice iz administracije.</p>',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<header class="visually-hidden">', false);
        $response->assertSee('<h1 class="h2 mb-2">Antikvarijat Vremeplov</h1>', false);
        $response->assertSee(
            'Antikvarne i rabljene knjige, stare razglednice, plakati, časopisi i kolekcionarski predmeti.'
        );
        $response->assertSee('Sadržaj naslovnice iz administracije.');
        $response->assertDontSee('aria-label="BOX NOW dostava"', false);
        $response->assertDontSee('boxnow-banner-desktop.png', false);
    }

    private function createCustomWidgetGroup(string $slug, string $title): int
    {
        return DB::table('widget_groups')->insertGetId([
            'template' => 'custom',
            'title' => $title,
            'slug' => $slug,
            'width' => 12,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCustomWidget(int $groupId, string $title, ?string $image): void
    {
        DB::table('widgets')->insert([
            'group_id' => $groupId,
            'title' => $title,
            'subtitle' => 'Tekst widgeta',
            'data' => serialize([
                'button_text' => 'Pogledajte ponudu',
                'right' => 'on',
                'eyebrow' => 'Besplatna dostava do 1.10.',
                'eyebrow_icon' => 'truck',
                'benefit_1_icon' => 'clock',
                'benefit_1_text' => 'Preuzimanje 24/7',
            ]),
            'image' => $image,
            'url' => '/knjige',
            'badge' => '#ffffff',
            'width' => 12,
            'sort_order' => 1,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
