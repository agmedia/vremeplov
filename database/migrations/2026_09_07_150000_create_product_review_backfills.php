<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductReviewBackfills extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('product_review_backfills')) {
            Schema::create('product_review_backfills', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->date('date_from');
                $table->date('date_to');
                $table->unsignedInteger('requested_limit');
                $table->unsignedSmallInteger('interval_seconds')->default(5);
                $table->unsignedInteger('eligible_count')->default(0);
                $table->unsignedInteger('total_count')->default(0);
                $table->unsignedInteger('processed_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->string('status', 20)->default('pending');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at'], 'review_backfills_status_created_index');
            });
        }

        if (! Schema::hasTable('product_review_backfill_items')) {
            Schema::create('product_review_backfill_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('backfill_id');
                $table->unsignedBigInteger('order_id');
                $table->string('status', 20)->default('pending');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('last_attempt_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->unique(['backfill_id', 'order_id'], 'review_backfill_items_batch_order_unique');
                $table->index(['backfill_id', 'status', 'id'], 'review_backfill_items_due_index');
                $table->index('order_id', 'review_backfill_items_order_index');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('product_review_backfill_items');
        Schema::dropIfExists('product_review_backfills');
    }
}
