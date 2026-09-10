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

    public function test_required_validation_messages_are_in_croatian(): void
    {
        Mail::fake();

        $this->from(route('contract-termination'))
            ->post(route('contract-termination.send'), [])
            ->assertRedirect(route('contract-termination'))
            ->assertSessionHasErrors([
                'full_name' => 'Upišite ime i prezime.',
                'email' => 'Upišite e-mail adresu.',
                'address' => 'Upišite ulicu i kućni broj.',
                'postal_code' => 'Upišite poštanski broj.',
                'city' => 'Upišite mjesto.',
                'country' => 'Upišite državu.',
                'order_number' => 'Upišite broj narudžbe ili računa.',
                'items' => 'Navedite artikle na koje se raskid odnosi.',
                'statement' => 'Za slanje je potrebno potvrditi izjavu o raskidu ugovora.',
            ]);

        $this->assertDatabaseCount('contract_terminations', 0);
        Mail::assertNothingSent();
    }

    public function test_format_validation_messages_are_in_croatian(): void
    {
        Mail::fake();

        $payload = $this->validPayload([
            'email' => 'neispravna-adresa',
            'phone' => '12+34+56',
            'iban' => 'HR4524020061100571695',
        ]);

        $this->from(route('contract-termination'))
            ->post(route('contract-termination.send'), $payload)
            ->assertRedirect(route('contract-termination'))
            ->assertSessionHasErrors([
                'email' => 'Upišite ispravnu e-mail adresu.',
                'phone' => 'Upišite ispravan broj telefona (6–15 znamenki).',
                'iban' => 'Upišite ispravan IBAN.',
            ]);

        $this->assertDatabaseCount('contract_terminations', 0);
        Mail::assertNothingSent();
    }

    /**
     * @dataProvider invalidDateProvider
     */
    public function test_invalid_or_illogical_dates_are_rejected(
        string $case,
        string $field,
        string $message
    ): void {
        Mail::fake();

        $dates = [
            'malformed_order_date' => [
                'order_date' => '10.09.2026',
            ],
            'future_order_date' => [
                'order_date' => now()->addDay()->toDateString(),
            ],
            'future_received_date' => [
                'received_date' => now()->addDay()->toDateString(),
            ],
            'received_before_order' => [
                'order_date' => now()->subDay()->toDateString(),
                'received_date' => now()->subDays(2)->toDateString(),
            ],
        ];

        $response = $this->from(route('contract-termination'))
            ->post(route('contract-termination.send'), $this->validPayload($dates[$case]));

        $response
            ->assertRedirect(route('contract-termination'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('contract_terminations', 0);
        Mail::assertNothingSent();
    }

    public function invalidDateProvider(): array
    {
        return [
            'malformed order date' => [
                'malformed_order_date',
                'order_date',
                'Upišite ispravan datum narudžbe.',
            ],
            'future order date' => [
                'future_order_date',
                'order_date',
                'Datum narudžbe ne može biti u budućnosti.',
            ],
            'future received date' => [
                'future_received_date',
                'received_date',
                'Datum primitka robe ne može biti u budućnosti.',
            ],
            'received date before order date' => [
                'received_before_order',
                'received_date',
                'Datum primitka robe ne može biti prije datuma narudžbe.',
            ],
        ];
    }

    /**
     * @dataProvider malformedArrayProvider
     */
    public function test_malformed_array_values_return_validation_errors_without_a_server_error(
        string $field,
        string $message
    ): void {
        Mail::fake();

        $response = $this->from(route('contract-termination'))
            ->post(route('contract-termination.send'), $this->validPayload([
                $field => ['unexpected'],
            ]));

        $response
            ->assertRedirect(route('contract-termination'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('contract_terminations', 0);
        Mail::assertNothingSent();
    }

    public function malformedArrayProvider(): array
    {
        return [
            'phone array' => ['phone', 'Broj telefona mora biti tekst.'],
            'IBAN array' => ['iban', 'IBAN mora biti tekst.'],
            'reCAPTCHA array' => [
                'recaptcha',
                'Sigurnosna provjera nije valjana. Osvježite stranicu i pokušajte ponovno.',
            ],
        ];
    }

    public function test_form_renders_accessible_inline_validation_attributes(): void
    {
        config([
            'services.recaptcha.bypass_local' => true,
            'services.recaptcha.sitekey' => 'test-site-key',
            'services.recaptcha.secret' => 'test-secret-key',
        ]);

        $response = $this->get(route('contract-termination'))->assertOk();
        $html = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/<form(?=[^>]*\bid="contract-termination-form")(?=[^>]*\bdata-inline-validation\b)(?=[^>]*\bnovalidate\b)[^>]*>/',
            $html
        );

        foreach ([
            'ct-name',
            'ct-email',
            'ct-phone',
            'ct-address',
            'ct-postal',
            'ct-city',
            'ct-country',
            'ct-order',
            'ct-order-date',
            'ct-received-date',
            'ct-items',
            'ct-iban',
            'ct-statement',
        ] as $controlId) {
            $quotedId = preg_quote($controlId, '/');
            $quotedFeedbackId = preg_quote($controlId . '-feedback', '/');

            $this->assertMatchesRegularExpression(
                '/<(?:input|textarea)(?=[^>]*\bid="' . $quotedId . '")'
                    . '(?=[^>]*\baria-invalid="false")'
                    . '(?=[^>]*\baria-describedby="[^"]*' . $quotedFeedbackId . '[^"]*")[^>]*>/',
                $html,
                'Control ' . $controlId . ' must expose its inline validation state and feedback.'
            );
            $this->assertStringContainsString('id="' . $controlId . '-feedback"', $html);
        }

        $this->assertStringContainsString('data-validation-phone=', $html);
        $this->assertStringContainsString('data-validation-iban=', $html);
        $this->assertStringContainsString('data-validation-not-before="#ct-order-date"', $html);
        $this->assertStringContainsString('js/front-form-validation.js', $html);

        $validatorPosition = strpos($html, 'js/front-form-validation.js');
        $recaptchaPosition = strpos(
            $html,
            'window.VremeplovRecaptcha.execute("contract_termination", "recaptcha")'
        );

        $this->assertNotFalse($validatorPosition);
        $this->assertNotFalse($recaptchaPosition);
        $this->assertLessThan($recaptchaPosition, $validatorPosition);
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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
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
            'recaptcha' => null,
            'contract_termination_form' => '1',
        ], $overrides);
    }
}
