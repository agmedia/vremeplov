<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddBoxNowShippingTrackingToOrders extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $this->addColumn('commentp', function (Blueprint $table) {
            $table->text('commentp')->nullable()->after('comment');
        });
        $this->addColumn('shipping_carrier', function (Blueprint $table) {
            $table->string('shipping_carrier', 32)->nullable()->after('shipping_code');
        });
        $this->addColumn('shipping_parcel_id', function (Blueprint $table) {
            $table->string('shipping_parcel_id', 191)->nullable()->after('tracking_code');
        });
        $this->addColumn('shipping_tracking_url', function (Blueprint $table) {
            $table->string('shipping_tracking_url', 500)->nullable()->after('shipping_parcel_id');
        });
        $this->addColumn('shipping_tracking_status_code', function (Blueprint $table) {
            $table->string('shipping_tracking_status_code', 64)->nullable()->after('shipping_tracking_url');
        });
        $this->addColumn('shipping_tracking_status', function (Blueprint $table) {
            $table->string('shipping_tracking_status', 255)->nullable()->after('shipping_tracking_status_code');
        });
        $this->addColumn('shipping_tracking_updated_at', function (Blueprint $table) {
            $table->timestamp('shipping_tracking_updated_at')->nullable()->after('shipping_tracking_status');
        });
        $this->addColumn('shipping_tracking_attempted_at', function (Blueprint $table) {
            $table->timestamp('shipping_tracking_attempted_at')->nullable()->after('shipping_tracking_updated_at');
        });
        $this->addColumn('shipping_tracking_payload', function (Blueprint $table) {
            $table->longText('shipping_tracking_payload')->nullable()->after('shipping_tracking_attempted_at');
        });

        $this->addIndex('shipping_tracking_attempted_at', 'orders_shipping_tracking_attempted_at_index');
        $this->addIndex('shipping_carrier', 'orders_shipping_carrier_index');
        $this->addIndex('shipping_parcel_id', 'orders_shipping_parcel_id_index');
        $this->addIndex('shipping_tracking_status_code', 'orders_shipping_tracking_status_code_index');
        $this->addIndex('shipping_tracking_updated_at', 'orders_shipping_tracking_updated_at_index');
    }

    public function down()
    {
        // Existing tracking history is intentionally retained on legacy stores.
    }

    private function addColumn(string $column, callable $definition): void
    {
        if (Schema::hasColumn('orders', $column)) {
            return;
        }

        Schema::table('orders', $definition);
    }

    private function addIndex(string $column, string $index): void
    {
        if ($this->indexExists($index)) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) use ($column, $index) {
            $table->index($column, $index);
        });
    }

    private function indexExists(string $index): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            foreach ($connection->select("PRAGMA index_list('orders')") as $existingIndex) {
                if (($existingIndex->name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        return DB::connection($connection->getName())
            ->table('information_schema.statistics')
            ->whereRaw('table_schema = database()')
            ->where('table_name', 'orders')
            ->where('index_name', $index)
            ->exists();
    }
}
