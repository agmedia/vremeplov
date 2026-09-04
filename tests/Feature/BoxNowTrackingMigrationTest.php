<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BoxNowTrackingMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_columns_and_indexes_are_installed_idempotently(): void
    {
        foreach ($this->columns() as $column) {
            $this->assertTrue(Schema::hasColumn('orders', $column), $column);
        }

        $indexes = collect(DB::select("PRAGMA index_list('orders')"))
            ->pluck('name')
            ->all();

        foreach ($this->indexes() as $index) {
            $this->assertContains($index, $indexes, $index);
        }

        (new \AddBoxNowShippingTrackingToOrders())->up();

        foreach ($this->columns() as $column) {
            $this->assertTrue(Schema::hasColumn('orders', $column), $column);
        }
    }

    private function columns(): array
    {
        return [
            'commentp',
            'shipping_carrier',
            'shipping_parcel_id',
            'shipping_tracking_url',
            'shipping_tracking_status_code',
            'shipping_tracking_status',
            'shipping_tracking_updated_at',
            'shipping_tracking_attempted_at',
            'shipping_tracking_payload',
        ];
    }

    private function indexes(): array
    {
        return [
            'orders_shipping_tracking_attempted_at_index',
            'orders_shipping_carrier_index',
            'orders_shipping_parcel_id_index',
            'orders_shipping_tracking_status_code_index',
            'orders_shipping_tracking_updated_at_index',
        ];
    }
}
