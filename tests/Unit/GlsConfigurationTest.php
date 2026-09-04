<?php

namespace Tests\Unit;

use App\Models\Front\Checkout\Shipping\Gls;
use Tests\TestCase;

class GlsConfigurationTest extends TestCase
{
    public function test_missing_credentials_fail_closed_before_a_network_request(): void
    {
        config([
            'services.gls.client_number' => null,
            'services.gls.username' => null,
            'services.gls.password' => null,
            'services.gls.pickup.contact_name' => null,
            'services.gls.pickup.contact_phone' => null,
            'services.gls.pickup.contact_email' => null,
            'services.gls.pickup.street' => null,
            'services.gls.pickup.house_number' => null,
            'services.gls.pickup.city' => null,
            'services.gls.pickup.zip_code' => null,
        ]);

        $result = (new Gls(['id' => 123]))->resolve();

        $this->assertArrayHasKey('PrepareLabelsError', $result);
        $this->assertStringContainsString(
            'Nedostaje GLS konfiguracija',
            $result['PrepareLabelsError'][0]['ErrorDescription']
        );
    }

    public function test_gls_source_uses_configuration_without_process_wide_runtime_overrides(): void
    {
        $source = (string) file_get_contents(app_path('Models/Front/Checkout/Shipping/Gls.php'));

        $this->assertStringContainsString("config('services.gls.client_number')", $source);
        $this->assertStringContainsString("config('services.gls.password')", $source);
        $this->assertStringNotContainsString('ini_set(', $source);
        $this->assertDoesNotMatchRegularExpression('/\\$pwd\\s*=\\s*[\'\"][^\'\"]+[\'\"]/', $source);
        $this->assertDoesNotMatchRegularExpression('/\\$clientNumber\\s*=\\s*\\d+/', $source);
    }
}
