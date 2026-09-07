<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReviewRequestQueryIndexes extends Migration
{
    public function up()
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(
                    ['order_status_id', 'created_at', 'id'],
                    'orders_review_request_candidates_index'
                );
            });
        }

        if (Schema::hasTable('order_history')) {
            Schema::table('order_history', function (Blueprint $table) {
                $table->index(
                    ['order_id', 'status', 'created_at'],
                    'order_history_review_request_index'
                );
            });
        }

        if (Schema::hasTable('order_products')) {
            Schema::table('order_products', function (Blueprint $table) {
                $table->index(
                    ['order_id', 'product_id'],
                    'order_products_review_request_index'
                );
            });
        }

        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->index(
                    ['order_id', 'product_id'],
                    'reviews_order_product_index'
                );
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropIndex('reviews_order_product_index');
            });
        }

        if (Schema::hasTable('order_products')) {
            Schema::table('order_products', function (Blueprint $table) {
                $table->dropIndex('order_products_review_request_index');
            });
        }

        if (Schema::hasTable('order_history')) {
            Schema::table('order_history', function (Blueprint $table) {
                $table->dropIndex('order_history_review_request_index');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_review_request_candidates_index');
            });
        }
    }
}
