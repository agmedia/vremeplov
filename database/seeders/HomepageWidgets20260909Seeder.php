<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class HomepageWidgets20260909Seeder extends Seeder
{
    public function run(): void
    {
        $this->assertSafeCurrentState();
        $this->backupCurrentState();

        DB::transaction(function (): void {
            $now = now();

            $sliderGroupId = $this->requiredGroupId('slider-index');
            $latestGroupId = $this->requiredGroupId('zadnje-dodano');
            $blogGroupId = $this->requiredGroupId('blog');

            $holidayWidgets = DB::table('widgets')
                ->where('group_id', $sliderGroupId)
                ->where('title', 'Velika Božićna Akcija - 20% Popusta na Sve Knjige!')
                ->get(['id']);

            if ($holidayWidgets->count() !== 1) {
                throw new RuntimeException('Expected exactly one Christmas slider widget; deployment stopped.');
            }

            DB::table('widgets')->where('id', $holidayWidgets->first()->id)
                ->update(['status' => 0, 'updated_at' => $now]);

            $this->upsertWidget($sliderGroupId, [
                'title' => 'Besplatna BOX NOW dostava',
                'subtitle' => 'Odaberite BOX NOW paketomat i preuzmite pošiljku kada vam odgovara — dostava je besplatna.',
                'description' => null,
                'data' => serialize([
                    'title' => 'Besplatna BOX NOW dostava',
                    'subtitle' => 'Odaberite BOX NOW paketomat i preuzmite pošiljku kada vam odgovara — dostava je besplatna.',
                    'button_text' => 'Pogledajte ponudu',
                    'url' => '/knjige',
                    'width' => '12',
                    'sort_order' => '1',
                    'right' => 'on',
                    'status' => 'on',
                    'badge' => '#ffffff',
                    'group_id' => (string) $sliderGroupId,
                    'group_template' => 'custom',
                    'eyebrow' => 'Besplatna dostava do 1.10.',
                    'benefit_1_icon' => 'clock',
                    'benefit_1_text' => 'Preuzimanje 24/7',
                    'benefit_2_icon' => 'location-dot',
                    'benefit_2_text' => 'Paketomat po izboru',
                    'benefit_3_icon' => 'box',
                    'benefit_3_text' => 'Brzo i jednostavno',
                    'eyebrow_icon' => 'truck',
                ]),
                'image' => 'media/img/boxnow-banner-mobile.jpg',
                'link' => null,
                'link_id' => null,
                'url' => '/knjige',
                'badge' => '#ffffff',
                'width' => '12',
                'sort_order' => 1,
                'status' => 1,
            ]);

            $this->updateExistingWidgetData($latestGroupId, 'Zadnje dodano', function (array $data) use ($latestGroupId): array {
                $data['url'] = '/knjige';
                $data['new'] = 'on';
                $data['group_id'] = (string) $latestGroupId;

                return $data;
            }, ['url' => '/knjige']);

            $blogIds = [
                $this->requiredPageId('cuvar-najstarijih-hrvatskih-slikovnica'),
                $this->requiredPageId('19th-international-exhibition-bratislava-collectors-days-2023'),
                $this->requiredPageId('nasta-rojc-slikarica-koja-je-zivjela-ispred-svog-vremena'),
            ];

            $this->updateExistingWidgetData($blogGroupId, 'Novosti i objave', function (array $data) use ($blogGroupId, $blogIds): array {
                $data['new'] = 'on';
                $data['group_id'] = (string) $blogGroupId;
                $data['list'] = collect($blogIds)->mapWithKeys(static fn (int $id): array => [$id => (string) $id])->all();

                return $data;
            });

            $this->seedCarousel(
                'najpopularnije',
                'Najpopularnije',
                'Najčešće pregledavane knjige u ponudi.',
                '/knjige',
                ['target' => 'product', 'popular' => 'on', 'group' => 'product', 'catalog_group' => 'knjige']
            );

            $this->seedCategoryCarousel(
                'popularno-iz-knjizevnosti',
                'Popularno iz književnosti',
                'Najčitaniji naslovi iz naše najveće kategorije.',
                'knjizevnost'
            );

            $this->seedCategoryCarousel(
                'algoritam',
                'Algoritam',
                'Popularni naslovi iz ponude Algoritma.',
                'algoritam'
            );

            $this->seedCategoryCarousel(
                'kuharice-i-kulinarstvo',
                'Kuharice i kulinarstvo',
                'Najpopularnije kuharice i knjige o kulinarstvu.',
                'kuharice-i-kulinarstvo'
            );

            $this->seedCategoryCarousel(
                'psihologija',
                'Psihologija',
                'Popularni naslovi iz psihologije i osobnog razvoja.',
                'psihologija'
            );

            $reviewsGroupId = $this->upsertGroup([
                'template' => 'page_carousel',
                'type' => null,
                'title' => 'Izdvojeni komentari',
                'slug' => 'izdvojeni-komentari',
                'width' => '12',
                'status' => 1,
            ]);

            $this->upsertWidget($reviewsGroupId, [
                'title' => 'Izdvojeni komentari',
                'subtitle' => 'Dojmovi naših kupaca.',
                'description' => null,
                'data' => serialize([
                    'title' => 'Izdvojeni komentari',
                    'target' => 'reviews',
                    'subtitle' => 'Dojmovi naših kupaca.',
                    'css' => null,
                    'featured_only' => 'on',
                    'background' => 'on',
                    'status' => 'on',
                    'group' => 'reviews',
                    'group_id' => (string) $reviewsGroupId,
                    'group_template' => 'page_carousel',
                ]),
                'image' => null,
                'link' => null,
                'link_id' => null,
                'url' => '/',
                'badge' => null,
                'width' => null,
                'sort_order' => 0,
                'status' => 1,
            ]);

            $desiredHomepage = $this->desiredHomepageDescription();
            $homepage = DB::table('pages')
                ->where('id', 1)
                ->where('slug', 'homepage')
                ->first(['id', 'description']);

            if (! $homepage) {
                throw new RuntimeException('Homepage page (ID 1) was not found; widget deployment stopped.');
            }

            if ((string) $homepage->description !== $desiredHomepage) {
                DB::table('pages')->where('id', $homepage->id)->update([
                    'description' => $desiredHomepage,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    private function seedCategoryCarousel(string $groupSlug, string $title, string $subtitle, string $categorySlug): void
    {
        $categories = DB::table('categories')
            ->where('group', 'knjige')
            ->where('slug', $categorySlug)
            ->get(['id']);

        if ($categories->count() !== 1) {
            throw new RuntimeException("Expected exactly one book category [{$categorySlug}]; widget deployment stopped.");
        }

        $category = $categories->first();

        $this->seedCarousel(
            $groupSlug,
            $title,
            $subtitle,
            '/knjige/' . $categorySlug,
            [
                'target' => 'product_category',
                'popular' => 'on',
                'group' => 'product_category',
                'list' => [(int) $category->id => (string) $category->id],
            ]
        );
    }

    private function seedCarousel(string $groupSlug, string $title, string $subtitle, string $url, array $options): void
    {
        $groupId = $this->upsertGroup([
            'template' => 'product_carousel',
            'type' => null,
            'title' => $title,
            'slug' => $groupSlug,
            'width' => '12',
            'status' => 1,
        ]);

        $this->upsertWidget($groupId, [
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => null,
            'data' => serialize(array_merge([
                'title' => $title,
                'target' => $options['target'],
                'subtitle' => $subtitle,
                'url' => $url,
                'css' => null,
            ], collect($options)->except('target')->all(), [
                'status' => 'on',
                'group_id' => (string) $groupId,
                'group_template' => 'product_carousel',
            ])),
            'image' => null,
            'link' => null,
            'link_id' => null,
            'url' => $url,
            'badge' => null,
            'width' => null,
            'sort_order' => 0,
            'status' => 1,
        ]);
    }

    private function upsertGroup(array $attributes): int
    {
        $now = now();
        $groups = DB::table('widget_groups')->where('slug', $attributes['slug'])->get(['id']);

        if ($groups->count() > 1) {
            throw new RuntimeException("Duplicate widget group slug [{$attributes['slug']}]; deployment stopped.");
        }

        $group = $groups->first();

        if ($group) {
            DB::table('widget_groups')->where('id', $group->id)->update(array_merge($attributes, ['updated_at' => $now]));

            return (int) $group->id;
        }

        return (int) DB::table('widget_groups')->insertGetId(array_merge($attributes, [
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    private function upsertWidget(int $groupId, array $attributes): int
    {
        $now = now();
        $widgets = DB::table('widgets')
            ->where('group_id', $groupId)
            ->where('title', $attributes['title'])
            ->get(['id']);

        if ($widgets->count() > 1) {
            throw new RuntimeException("Duplicate widget [{$attributes['title']}]; deployment stopped.");
        }

        $widget = $widgets->first();

        if ($widget) {
            DB::table('widgets')->where('id', $widget->id)->update(array_merge($attributes, ['updated_at' => $now]));

            return (int) $widget->id;
        }

        return (int) DB::table('widgets')->insertGetId(array_merge($attributes, [
            'group_id' => $groupId,
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    private function requiredGroupId(string $slug): int
    {
        $ids = DB::table('widget_groups')->where('slug', $slug)->pluck('id');

        if ($ids->count() !== 1) {
            throw new RuntimeException("Expected exactly one required widget group [{$slug}]; deployment stopped.");
        }

        return (int) $ids->first();
    }

    private function requiredPageId(string $slug): int
    {
        $ids = DB::table('pages')->where('group', 'blog')->where('slug', $slug)->pluck('id');

        if ($ids->count() !== 1) {
            throw new RuntimeException("Expected exactly one blog page [{$slug}]; widget deployment stopped.");
        }

        return (int) $ids->first();
    }

    private function updateExistingWidgetData(int $groupId, string $title, callable $mutator, array $columns = []): void
    {
        $widgets = DB::table('widgets')->where('group_id', $groupId)->where('title', $title)->get();

        if ($widgets->count() !== 1) {
            throw new RuntimeException("Expected exactly one existing widget [{$title}]; deployment stopped.");
        }

        $widget = $widgets->first();
        $data = @unserialize((string) $widget->data, ['allowed_classes' => false]);

        if (! is_array($data)) {
            throw new RuntimeException("Widget [{$title}] has invalid serialized data; deployment stopped.");
        }

        DB::table('widgets')->where('id', $widget->id)->update(array_merge($columns, [
            'data' => serialize($mutator($data)),
            'updated_at' => now(),
        ]));
    }

    private function assertSafeCurrentState(): void
    {
        foreach (['slider-index', 'zadnje-dodano', 'blog', 'najpopularnije', 'izdvojeni-komentari', 'popularno-iz-knjizevnosti', 'algoritam', 'kuharice-i-kulinarstvo', 'psihologija'] as $slug) {
            if (DB::table('widget_groups')->where('slug', $slug)->count() > 1) {
                throw new RuntimeException("Duplicate widget group slug [{$slug}]; deployment stopped before backup/write.");
            }
        }

        $homepage = DB::table('pages')->where('id', 1)->where('slug', 'homepage')->first(['description']);
        $current = $homepage ? str_replace(["\r\n", "\r"], "\n", trim((string) $homepage->description)) : null;
        $allowed = [
            str_replace(["\r\n", "\r"], "\n", trim($this->legacyHomepageDescription())),
            trim($this->desiredHomepageDescription()),
        ];

        if ($current === null || ! in_array($current, $allowed, true)) {
            throw new RuntimeException('Homepage content differs from both the known old and desired layouts; deployment stopped.');
        }
    }

    private function desiredHomepageDescription(): string
    {
        return implode("\n", [
            '++slider-index++',
            '++zadnje-dodano++',
            '++najpopularnije++',
            '++popularno-iz-knjizevnosti++',
            '++izdvojeni-komentari++',
            '++algoritam++',
            '++kuharice-i-kulinarstvo++',
            '++psihologija++',
            '++blog++',
        ]);
    }

    private function legacyHomepageDescription(): string
    {
        return "<br><p>++knjige++</p>\n\n<p>++zadnje-dodano++</p>\n\n<p>++izdvojene-kategorije-kniga++</p>\n\n<p>++blog++</p>";
    }

    private function backupCurrentState(): void
    {
        $groupSlugs = [
            'slider-index',
            'zadnje-dodano',
            'blog',
            'najpopularnije',
            'izdvojeni-komentari',
            'popularno-iz-knjizevnosti',
            'algoritam',
            'kuharice-i-kulinarstvo',
            'psihologija',
        ];

        $groupIds = DB::table('widget_groups')->whereIn('slug', $groupSlugs)->pluck('id');
        $backup = [
            'created_at' => now()->toIso8601String(),
            'widget_groups' => DB::table('widget_groups')->whereIn('id', $groupIds)->orderBy('id')->get(),
            'widgets' => DB::table('widgets')
                ->whereIn('group_id', $groupIds)
                ->orWhere('title', 'Velika Božićna Akcija - 20% Popusta na Sve Knjige!')
                ->orderBy('id')
                ->get(),
            'homepage' => DB::table('pages')->where('id', 1)->first(),
        ];

        $path = 'deploy-backups/homepage-widgets-before-' . now()->format('Ymd-His-u') . '.json';
        Storage::disk('local')->put($path, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $this->command?->info('Widget backup saved to storage/app/' . $path);
    }
}
