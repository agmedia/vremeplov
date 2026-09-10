<?php

namespace Tests\Feature;

use App\Models\Back\Settings\Faq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FaqManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_faq_page_orders_active_questions_and_main_navigation_links_to_it(): void
    {
        DB::table('faq')->insert([
            [
                'title' => 'Kasnije pitanje',
                'description' => '<p>Drugi odgovor.</p>',
                'lang' => 'hr',
                'sort_order' => 20,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Prvo pitanje',
                'description' => '<p>Prvi odgovor.</p>',
                'lang' => 'hr',
                'sort_order' => 10,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Skriveno pitanje',
                'description' => '<p>Skriveni odgovor.</p>',
                'lang' => 'hr',
                'sort_order' => 1,
                'status' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->get(route('faq'));

        $response->assertOk();
        $response->assertSeeInOrder(['Prvo pitanje', 'Kasnije pitanje']);
        $response->assertDontSee('Skriveno pitanje');
        $this->assertGreaterThanOrEqual(2, substr_count($response->getContent(), 'href="' . route('faq') . '"'));
        $response->assertSee('Česta pitanja');
        $response->assertSee('css/front-faq.css?v=1.0.0', false);
        $response->assertSee('faq-list', false);
    }

    public function test_admin_can_create_and_update_a_faq_without_creating_a_duplicate(): void
    {
        $user = User::factory()->create();

        $createResponse = $this->actingAs($user)->post(route('faqs.store'), [
            'title' => 'Početno pitanje',
            'description' => '<p>Početni odgovor.</p>',
            'status' => 'on',
        ]);

        $faq = Faq::where('title', 'Početno pitanje')->firstOrFail();

        $createResponse->assertRedirect(route('faqs.edit', ['faq' => $faq]));
        $this->assertSame('0', (string) $faq->sort_order);

        $updateResponse = $this->actingAs($user)->patch(route('faqs.update', ['faq' => $faq]), [
            'title' => 'Izmijenjeno pitanje',
            'description' => '<p>Izmijenjeni odgovor.</p>',
            'status' => 'on',
        ]);

        $updateResponse->assertRedirect(route('faqs.edit', ['faq' => $faq]));
        $this->assertDatabaseCount('faq', 1);
        $this->assertDatabaseHas('faq', [
            'id' => $faq->id,
            'title' => 'Izmijenjeno pitanje',
            'description' => '<p>Izmijenjeni odgovor.</p>',
        ]);
    }
}
