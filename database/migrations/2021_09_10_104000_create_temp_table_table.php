<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTempTableTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('temp_table', function (Blueprint $table) {
            $table->bigInteger('item_id');
            $table->string('sku')->nullable();
            $table->text('text')->nullable();
            $table->longText('longtext')->nullable();
            $table->integer('quantity')->nullable();
            $table->decimal('special', 15, 4)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('temp_table');
    }
}
