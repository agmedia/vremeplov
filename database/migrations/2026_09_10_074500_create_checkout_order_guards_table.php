<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCheckoutOrderGuardsTable extends Migration
{
    public function up()
    {
        Schema::create('checkout_order_guards', function (Blueprint $table) {
            $table->char('fingerprint', 64)->primary();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('checkout_order_guards');
    }
}
