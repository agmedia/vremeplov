<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContractTerminationsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('contract_terminations')) {
            return;
        }

        Schema::create('contract_terminations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('reference', 32)->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('order_number', 80)->index();
            $table->string('full_name', 150);
            $table->string('email', 190)->index();
            $table->string('phone', 50)->nullable();
            $table->string('address', 190);
            $table->string('postal_code', 20);
            $table->string('city', 100);
            $table->string('country', 80);
            $table->date('order_date')->nullable();
            $table->date('received_date')->nullable();
            $table->text('items');
            $table->string('iban', 50)->nullable();
            $table->boolean('statement')->default(true);
            $table->string('status', 32)->default('received')->index();
            $table->text('internal_note')->nullable();
            $table->timestamp('submitted_at')->index();
            $table->timestamp('consumer_notified_at')->nullable();
            $table->timestamp('admin_notified_at')->nullable();
            $table->text('notification_error')->nullable();
            $table->unsignedBigInteger('handled_by')->nullable()->index();
            $table->timestamp('handled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('contract_terminations');
    }
}
