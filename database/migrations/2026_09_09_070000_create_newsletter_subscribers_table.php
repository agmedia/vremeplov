<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNewsletterSubscribersTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('newsletter_subscribers')) {
            return;
        }

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('email', 191)->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('source', 50)->default('homepage');
            $table->boolean('gdpr')->default(true);
            $table->boolean('status')->default(true);
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'source']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
}
