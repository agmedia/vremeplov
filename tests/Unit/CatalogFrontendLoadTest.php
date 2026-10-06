<?php

namespace Tests\Unit;

use Tests\TestCase;

class CatalogFrontendLoadTest extends TestCase
{
    public function test_catalog_embeds_a_safely_encoded_initial_paginator_for_vue(): void
    {
        $view = file_get_contents(resource_path('views/front/catalog/category/index.blade.php'));
        $component = file_get_contents(resource_path('js/front/filter/components/ProductsList/ProductsList.vue'));

        $this->assertStringContainsString(":initial-products='@json(\$products->toArray())'", $view);
        $this->assertStringContainsString('initialProductsPending: initialProducts !== null', $component);
        $this->assertStringContainsString('if (this.initialProductsPending)', $component);
        $this->assertStringContainsString('if (routeSignature === this.lastRouteSignature)', $component);
    }

    public function test_large_entity_facets_are_only_loaded_when_the_section_is_opened(): void
    {
        $component = file_get_contents(resource_path('js/front/filter/components/Filter/Filter.vue'));

        $this->assertStringNotContainsString('deferEntityFiltersLoad', $component);
        $this->assertStringNotContainsString('requestIdleCallback', $component);
        $this->assertStringContainsString("section === 'authors' && (!this.authors_loaded || this.authors_dirty)", $component);
        $this->assertStringContainsString("section === 'publishers' && (!this.publishers_loaded || this.publishers_dirty)", $component);
    }
}
