<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAbandonedCartRemindersTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('abandoned_cart_reminders')) {
            return;
        }

        Schema::create('abandoned_cart_reminders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedTinyInteger('sequence');
            $table->timestamp('scheduled_for');
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('recipient_email', 191);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'sequence'], 'abandoned_cart_order_sequence_unique');
            $table->index(['sent_at', 'next_attempt_at'], 'abandoned_cart_due_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('abandoned_cart_reminders');
    }
}
