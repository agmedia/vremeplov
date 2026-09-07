<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShippingTrackingEmailSentAtToOrders extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'shipping_tracking_email_sent_at')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('shipping_tracking_email_sent_at')->nullable()->after('shipping_tracking_updated_at');
        });
    }

    public function down()
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'shipping_tracking_email_sent_at')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_tracking_email_sent_at');
        });
    }
}
