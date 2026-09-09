<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminRichTextEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_info_page_uses_the_same_image_enabled_editor_as_the_blog(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('pages.create'));

        $response->assertOk();
        $response->assertSee('js/plugins/ckeditor5-classic/build/ckeditor.js', false);
        $response->assertSee('ClassicEditor', false);
        $response->assertSee(route('pages.upload.image'), false);
        $response->assertSee("stickyPanel.isActive = true", false);
        $response->assertDontSee("CKEDITOR.replace", false);
    }

    public function test_info_page_editor_can_upload_an_image(): void
    {
        Storage::fake('page');

        $response = $this->actingAs(User::factory()->create())->postJson(route('pages.upload.image'), [
            'upload' => UploadedFile::fake()->image('tekst-slika.jpg', 900, 600),
            'page_id' => 42,
        ]);

        $response->assertOk()
            ->assertJson([
                'uploaded' => true,
            ]);

        $fileName = $response->json('fileName');

        $this->assertNotEmpty($fileName);
        Storage::disk('page')->assertExists('42/' . $fileName);
    }

    public function test_info_page_editor_rejects_non_image_uploads(): void
    {
        Storage::fake('page');

        $response = $this->actingAs(User::factory()->create())->postJson(route('pages.upload.image'), [
            'upload' => UploadedFile::fake()->createWithContent('skripta.php', '<?php echo "x";'),
            'page_id' => 42,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('upload');
        $this->assertSame([], Storage::disk('page')->allFiles());
    }
}
