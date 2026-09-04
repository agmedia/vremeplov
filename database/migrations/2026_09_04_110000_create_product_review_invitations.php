<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductReviewInvitations extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('product_review_invitations')) {
            Schema::create('product_review_invitations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('order_id')->unique();
                $table->char('token_hash', 64)->unique();
                $table->string('recipient_email', 191);
                $table->string('recipient_email_normalized', 191);
                $table->string('recipient_name', 191);
                $table->timestamp('eligible_at')->index();
                $table->timestamp('sent_at')->nullable()->index();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamp('last_attempt_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->index(
                    ['recipient_email_normalized', 'sent_at'],
                    'review_invitations_email_sent_index'
                );
            });
        }

        if (Schema::hasTable('reviews') && ! Schema::hasColumn('reviews', 'order_product_id')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->unsignedBigInteger('order_product_id')->nullable()->unique()->after('order_id');
                $table->unsignedBigInteger('invitation_id')->nullable()->index()->after('order_product_id');
                $table->boolean('is_verified_purchase')->default(false)->after('status');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'order_product_id')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropUnique(['order_product_id']);
                $table->dropIndex(['invitation_id']);
                $table->dropColumn(['order_product_id', 'invitation_id', 'is_verified_purchase']);
            });
        }

        Schema::dropIfExists('product_review_invitations');
    }
}
