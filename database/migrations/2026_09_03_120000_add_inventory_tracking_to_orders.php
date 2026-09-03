<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInventoryTrackingToOrders extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('inventory_reserved_at')->nullable()->index();
            $table->timestamp('inventory_committed_at')->nullable();
            $table->timestamp('inventory_released_at')->nullable();
            $table->timestamp('inventory_reservation_expires_at')->nullable()->index();
            $table->unsignedInteger('inventory_reservation_version')->default(0);
            $table->string('inventory_allocation_error', 500)->nullable();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('reservation_version');
            $table->string('action', 16);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('stock_before')->nullable();
            $table->unsignedInteger('stock_after')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'reservation_version'], 'inventory_movements_order_version');
            $table->index(['product_id', 'created_at'], 'inventory_movements_product_date');
            $table->unique(
                ['order_id', 'product_id', 'reservation_version', 'action'],
                'inventory_movement_once'
            );
        });

    }

    public function down()
    {
        Schema::dropIfExists('inventory_movements');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['inventory_reserved_at']);
            $table->dropIndex(['inventory_reservation_expires_at']);
            $table->dropColumn([
                'inventory_reserved_at',
                'inventory_committed_at',
                'inventory_released_at',
                'inventory_reservation_expires_at',
                'inventory_reservation_version',
                'inventory_allocation_error',
            ]);
        });
    }

}
