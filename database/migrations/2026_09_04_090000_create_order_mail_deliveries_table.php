<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderMailDeliveriesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('order_mail_deliveries')) {
            return;
        }

        Schema::create('order_mail_deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->string('type', 32);
            $table->string('recipient', 191);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'type'], 'order_mail_delivery_once');
            $table->index(['sent_at', 'next_attempt_at'], 'order_mail_delivery_pending');
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_mail_deliveries');
    }
}
