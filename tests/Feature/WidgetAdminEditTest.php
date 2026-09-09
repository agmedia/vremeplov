<?php

namespace Tests\Feature;

use App\Models\Back\Widget\Widget;
use App\Models\Back\Widget\WidgetGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetAdminEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_slider_widget_edit_form_renders(): void
    {
        $user = User::factory()->create();
        $group = WidgetGroup::query()->create([
            'template' => 'custom',
            'title' => 'Slider naslovnice',
            'slug' => 'slider-index',
            'width' => '12',
            'status' => true,
        ]);
        $widget = Widget::query()->create([
            'group_id' => $group->id,
            'title' => 'Besplatna BOX NOW dostava',
            'data' => serialize([
                'eyebrow' => 'Besplatna dostava do 1.10.',
            ]),
            'url' => '/knjige',
            'width' => '12',
            'sort_order' => 1,
            'status' => true,
        ]);

        $this->actingAs($user)
            ->get(route('widget.edit', ['widget' => $widget->id]))
            ->assertOk()
            ->assertViewIs('back.widget.templates.custom')
            ->assertSee('Uredi Widget')
            ->assertSee('Besplatna BOX NOW dostava')
            ->assertSee('Istaknuta poruka i pogodnosti');
    }
}
