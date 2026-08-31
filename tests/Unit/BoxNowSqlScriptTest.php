<?php

namespace Tests\Unit;

use Tests\TestCase;

class BoxNowSqlScriptTest extends TestCase
{
    public function test_live_sql_is_guarded_idempotent_and_non_destructive(): void
    {
        $sql = (string) file_get_contents(base_path('database/008_add_boxnow_shipping_tracking.sql'));
        $columns = [
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
        $indexes = [
            'orders_shipping_tracking_attempted_at_index',
            'orders_shipping_carrier_index',
            'orders_shipping_parcel_id_index',
            'orders_shipping_tracking_status_code_index',
            'orders_shipping_tracking_updated_at_index',
        ];

        foreach ($columns as $column) {
            $this->assertStringContainsString("COLUMN_NAME = '{$column}'", $sql);
            $this->assertSame(1, substr_count($sql, "ADD COLUMN `{$column}`"));
        }

        foreach ($indexes as $index) {
            $this->assertStringContainsString("INDEX_NAME = '{$index}'", $sql);
            $this->assertSame(1, substr_count($sql, "ADD INDEX `{$index}`"));
        }

        $this->assertSame(14, substr_count($sql, 'PREPARE stmt FROM @sql;'));
        $this->assertSame(14, substr_count($sql, 'EXECUTE stmt;'));
        $this->assertSame(14, substr_count($sql, 'DEALLOCATE PREPARE stmt;'));
        $this->assertDoesNotMatchRegularExpression('/\b(?:DROP|TRUNCATE|DELETE|RENAME)\b/i', $sql);
        $this->assertDoesNotMatchRegularExpression('/\bUPDATE\s+`?orders`?\b/i', $sql);
    }
}
