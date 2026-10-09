<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMailchimpSyncMetadataToNewsletterSubscribers extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('newsletter_subscribers') || Schema::hasColumn('newsletter_subscribers', 'mailchimp_sync_status')) {
            return;
        }
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->string('mailchimp_sync_status', 32)->default('pending');
            $table->string('mailchimp_member_status', 32)->nullable();
            $table->string('mailchimp_last_error', 80)->nullable();
            $table->unsignedInteger('mailchimp_sync_attempts')->default(0);
            $table->timestamp('mailchimp_synced_at')->nullable();
            $table->timestamp('mailchimp_last_attempt_at')->nullable();
            $table->timestamp('mailchimp_next_attempt_at')->nullable();
            $table->index(['mailchimp_sync_status', 'mailchimp_next_attempt_at'], 'newsletter_mailchimp_retry_idx');
        });
    }

    public function down()
    {
        if (! Schema::hasTable('newsletter_subscribers') || ! Schema::hasColumn('newsletter_subscribers', 'mailchimp_sync_status')) {
            return;
        }
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropIndex('newsletter_mailchimp_retry_idx');
            $table->dropColumn([
                'mailchimp_sync_status', 'mailchimp_member_status', 'mailchimp_last_error',
                'mailchimp_sync_attempts', 'mailchimp_synced_at', 'mailchimp_last_attempt_at', 'mailchimp_next_attempt_at',
            ]);
        });
    }
}
