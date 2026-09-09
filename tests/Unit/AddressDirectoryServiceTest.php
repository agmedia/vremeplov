<?php

namespace Tests\Unit;

use App\Services\AddressDirectoryService;
use Tests\TestCase;

class AddressDirectoryServiceTest extends TestCase
{
    public function test_it_finds_city_from_croatian_postal_code(): void
    {
        $place = app(AddressDirectoryService::class)->findByPostal('21 000');

        $this->assertSame('21000', $place['postal_code']);
        $this->assertSame('Split', $place['city']);
    }

    public function test_it_finds_postal_code_from_city_without_case_or_diacritic_sensitivity(): void
    {
        $place = app(AddressDirectoryService::class)->findByCity('cakovec');

        $this->assertSame('40000', $place['postal_code']);
        $this->assertSame('ČAKOVEC', $place['city']);
    }

    public function test_it_uses_the_primary_postal_code_when_a_city_has_multiple_codes(): void
    {
        $place = app(AddressDirectoryService::class)->findByCity('Zagreb');

        $this->assertSame('10000', $place['postal_code']);
        $this->assertSame('Zagreb', $place['city']);
    }

    public function test_it_does_not_autofill_places_for_another_country(): void
    {
        $this->assertNull(app(AddressDirectoryService::class)->findByPostal('10000', 'Germany'));
        $this->assertNull(app(AddressDirectoryService::class)->findByCity('Zagreb', 'Germany'));
    }
}
