<?php

namespace Tests\Feature;

use App\Mail\ContractTerminationConfirmation;
use App\Mail\ContractTerminationMessage;
use App\Models\ContractTermination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContractTerminationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.admin' => 'admin@example.test',
            'services.recaptcha.sitekey' => null,
            'services.recaptcha.secret' => null,
        ]);
    }

    public function test_form_is_available(): void
    {
        $this->get(route('contract-termination'))
            ->assertOk()
            ->assertSee('Obrazac za jednostrani raskid ugovora');
    }

    public function test_valid_statement_is_sent_to_admin_and_consumer(): void
    {
        Mail::fake();

        $response = $this->post(route('contract-termination.send'), [
            'full_name' => 'Ana Anić',
            'email' => 'ana@example.test',
            'phone' => '091 123 4567',
            'address' => 'Ilica 1',
            'postal_code' => '10000',
            'city' => 'Zagreb',
            'country' => 'HR',
            'order_number' => '12345',
            'order_date' => now()->subDays(5)->toDateString(),
            'received_date' => now()->subDays(2)->toDateString(),
            'items' => 'Knjiga, 1 komad',
            'iban' => '',
            'statement' => '1',
            'website' => '',
        ]);

        $response->assertRedirect(route('contract-termination'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('contract_terminations', [
            'order_number' => '12345',
            'email' => 'ana@example.test',
            'status' => 'received',
        ]);
        Mail::assertSent(ContractTerminationMessage::class, 1);
        Mail::assertSent(ContractTerminationConfirmation::class, 1);
    }

    public function test_statement_and_order_details_are_required(): void
    {
        Mail::fake();

        $this->from(route('contract-termination'))
            ->post(route('contract-termination.send'), [
                'full_name' => 'Ana Anić',
                'email' => 'ana@example.test',
            ])
            ->assertRedirect(route('contract-termination'))
            ->assertSessionHasErrors(['address', 'postal_code', 'city', 'country', 'order_number', 'items', 'statement']);

        Mail::assertNothingSent();
    }

    public function test_both_messages_render_the_submitted_reference(): void
    {
        $data = [
            'full_name' => 'Ana Anić',
            'email' => 'ana@example.test',
            'phone' => null,
            'address' => 'Ilica 1',
            'postal_code' => '10000',
            'city' => 'Zagreb',
            'country' => 'HR',
            'order_number' => 'VM-12345',
            'order_date' => null,
            'received_date' => null,
            'items' => 'Knjiga, 1 komad',
            'iban' => null,
            'statement' => true,
            'submitted_at' => now(),
        ];

        $this->assertStringContainsString('VM-12345', (new ContractTerminationMessage($data))->render());
        $this->assertStringContainsString('VM-12345', (new ContractTerminationConfirmation($data))->render());
    }

    public function test_saved_statement_is_visible_and_manageable_in_admin(): void
    {
        $termination = ContractTermination::query()->create([
            'reference' => 'JR-20260908-ABC123',
            'order_number' => '8391',
            'full_name' => 'Tomislav Jureša',
            'email' => 'tomislav@example.test',
            'address' => 'Ilica 1',
            'postal_code' => '10000',
            'city' => 'Zagreb',
            'country' => 'HR',
            'items' => 'Testna knjiga',
            'statement' => true,
            'status' => ContractTermination::STATUS_RECEIVED,
            'submitted_at' => now(),
        ]);
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('contract-terminations.index'))
            ->assertOk()
            ->assertSee('JR-20260908-ABC123');

        $this->actingAs($admin)
            ->patch(route('contract-terminations.update', $termination), [
                'status' => ContractTermination::STATUS_PROCESSING,
                'internal_note' => 'Kupac kontaktiran.',
            ])
            ->assertRedirect(route('contract-terminations.show', $termination));

        $this->assertDatabaseHas('contract_terminations', [
            'id' => $termination->id,
            'status' => ContractTermination::STATUS_PROCESSING,
            'internal_note' => 'Kupac kontaktiran.',
            'handled_by' => $admin->id,
        ]);
    }
}
