<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWishlistTable extends Migration
{
    public function up()
    {
        Schema::create('wishlist', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->default(0);
            $table->string('email');
            $table->unsignedBigInteger('product_id')->default(0);
            $table->tinyInteger('sent')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->index(['product_id', 'sent']);
            $table->index(['email', 'product_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('wishlist');
    }
}
