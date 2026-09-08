<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSourceToAbandonedCartReminders extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('abandoned_cart_reminders')) {
            return;
        }

        Schema::table('abandoned_cart_reminders', function (Blueprint $table) {
            if (! Schema::hasColumn('abandoned_cart_reminders', 'source')) {
                $table->string('source', 16)->default('automatic')->after('sequence');
            }
            if (! Schema::hasColumn('abandoned_cart_reminders', 'sent_by')) {
                $table->unsignedBigInteger('sent_by')->nullable()->after('recipient_email')->index();
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('abandoned_cart_reminders')) {
            return;
        }

        Schema::table('abandoned_cart_reminders', function (Blueprint $table) {
            if (Schema::hasColumn('abandoned_cart_reminders', 'sent_by')) {
                $table->dropColumn('sent_by');
            }
            if (Schema::hasColumn('abandoned_cart_reminders', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
}
