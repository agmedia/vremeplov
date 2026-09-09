<?php

namespace Tests\Feature;

use App\Mail\BookPurchaseMessage;
use App\Models\BookPurchaseRequest;
use App\Models\User;
use App\Services\BookPurchaseContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookPurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        config(['mail.admin' => 'admin@example.test']);
    }

    public function test_public_page_has_required_photo_upload_without_example_photos(): void
    {
        $this->get(route('book-purchase.create'))
            ->assertOk()
            ->assertSee('Otkup knjiga')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="photos[]"', false)
            ->assertSee('multiple required', false)
            ->assertDontSee('otkup-knjiga-primjer', false);
    }

    public function test_valid_request_stores_private_photos_and_notifies_admin(): void
    {
        $response = $this->post(route('book-purchase.store'), [
            'full_name' => 'Ana Anić',
            'postal_code' => '10000',
            'email' => 'ana@example.test',
            'phone' => '091 123 4567',
            'photos' => [
                UploadedFile::fake()->image('polica.jpg', 1000, 700)->size(500),
                UploadedFile::fake()->image('hrptovi.png', 800, 900)->size(650),
            ],
            'privacy' => '1',
            'website' => '',
        ]);

        $response->assertRedirect(route('book-purchase.create'));
        $response->assertSessionHas('success');

        $purchase = BookPurchaseRequest::query()->firstOrFail();
        $this->assertSame('ana@example.test', $purchase->email);
        $this->assertCount(2, $purchase->photos);
        $this->assertStringStartsWith('OK-', $purchase->reference);

        foreach ($purchase->photos as $photo) {
            Storage::disk('local')->assertExists($photo['path']);
            $this->assertStringStartsWith('book-purchases/' . $purchase->reference . '/', $photo['path']);
        }

        Mail::assertSent(BookPurchaseMessage::class, function ($mail) use ($purchase) {
            return $mail->purchase->is($purchase);
        });
    }

    public function test_photos_and_privacy_consent_are_required(): void
    {
        $this->from(route('book-purchase.create'))
            ->post(route('book-purchase.store'), [
                'full_name' => 'Ana Anić',
                'postal_code' => '10000',
                'email' => 'ana@example.test',
                'phone' => '091 123 4567',
                'website' => '',
            ])
            ->assertRedirect(route('book-purchase.create'))
            ->assertSessionHasErrors(['photos', 'privacy']);

        $this->assertDatabaseCount('book_purchase_requests', 0);
        Mail::assertNothingSent();
    }

    public function test_admin_can_filter_open_update_and_delete_a_request_with_its_photos(): void
    {
        Storage::disk('local')->put('book-purchases/OK-20260909-ABC123/01-test.jpg', 'image-bytes');
        $purchase = BookPurchaseRequest::query()->create([
            'reference' => 'OK-20260909-ABC123',
            'full_name' => 'Ivo Ivić',
            'postal_code' => '21000',
            'email' => 'ivo@example.test',
            'phone' => '091 000 0000',
            'photos' => [[
                'path' => 'book-purchases/OK-20260909-ABC123/01-test.jpg',
                'name' => 'knjige.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 11,
            ]],
            'status' => BookPurchaseRequest::STATUS_RECEIVED,
            'submitted_at' => now(),
        ]);
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('book-purchases.index', ['search' => 'ivo@example.test']))
            ->assertOk()
            ->assertSee('OK-20260909-ABC123');

        $this->actingAs($admin)
            ->get(route('book-purchases.show', $purchase))
            ->assertOk()
            ->assertSee('knjige.jpg');

        $this->actingAs($admin)
            ->get(route('book-purchases.photos.show', [$purchase, 0]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($admin)
            ->patch(route('book-purchases.update', $purchase), [
                'status' => BookPurchaseRequest::STATUS_PROCESSING,
                'internal_note' => 'Kupac je kontaktiran.',
            ])
            ->assertRedirect(route('book-purchases.show', $purchase));

        $this->assertDatabaseHas('book_purchase_requests', [
            'id' => $purchase->id,
            'status' => BookPurchaseRequest::STATUS_PROCESSING,
            'internal_note' => 'Kupac je kontaktiran.',
            'handled_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('book-purchases.destroy', $purchase))
            ->assertRedirect(route('book-purchases.index'));

        $this->assertDatabaseMissing('book_purchase_requests', ['id' => $purchase->id]);
        Storage::disk('local')->assertMissing('book-purchases/OK-20260909-ABC123/01-test.jpg');
    }

    public function test_admin_can_edit_all_public_page_content(): void
    {
        $content = app(BookPurchaseContentService::class)->defaults();
        $content['title'] = 'Ponudite knjige Vremeplovu';
        $content['intro_html'] = '<p>Poseban tekst iz administracije.</p>';
        $content['submit_label'] = 'Pošalji ponudu';
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('book-purchases.content.edit'))
            ->assertOk()
            ->assertSee('admin-rich-text-editor.js', false)
            ->assertSee('VremeplovRichTextEditor.create', false);

        $this->actingAs($admin)
            ->patch(route('book-purchases.content.update'), $content)
            ->assertRedirect(route('book-purchases.content.edit'));

        $this->get(route('book-purchase.create'))
            ->assertOk()
            ->assertSee('Ponudite knjige Vremeplovu')
            ->assertSee('Poseban tekst iz administracije.')
            ->assertSee('Pošalji ponudu');
    }

    public function test_book_purchase_navigation_is_available_on_desktop_and_mobile(): void
    {
        $response = $this->get(route('book-purchase.create'));

        $response->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), '<span>Otkup knjiga</span>'));
    }
}
