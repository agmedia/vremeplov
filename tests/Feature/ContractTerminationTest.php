<?php

namespace Tests\Feature;

use App\Mail\ContractTerminationConfirmation;
use App\Mail\ContractTerminationMessage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContractTerminationTest extends TestCase
{
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
}
