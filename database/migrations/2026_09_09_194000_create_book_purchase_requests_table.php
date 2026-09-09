<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBookPurchaseRequestsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('book_purchase_requests')) {
            return;
        }

        Schema::create('book_purchase_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('reference', 32)->unique();
            $table->string('full_name', 150)->index();
            $table->string('postal_code', 20);
            $table->string('email', 190)->index();
            $table->string('phone', 50);
            $table->json('photos');
            $table->string('status', 32)->default('received')->index();
            $table->text('internal_note')->nullable();
            $table->timestamp('submitted_at')->index();
            $table->unsignedBigInteger('handled_by')->nullable()->index();
            $table->timestamp('handled_at')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('book_purchase_requests');
    }
}
