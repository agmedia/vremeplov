<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentAttemptSnapshotToOrders extends Migration
{
    private const LOOKUP_INDEX = 'orders_payment_attempt_provider_reference_unique';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payment_attempt_started_at')->nullable()->index();
            $table->string('payment_attempt_provider', 32)->nullable();
            $table->string('payment_attempt_reference', 191)->nullable();
            $table->unsignedBigInteger('payment_expected_amount_minor')->nullable();
            $table->char('payment_expected_currency', 3)->nullable();
            $table->string('payment_attempt_environment', 16)->nullable();
            $table->string('payment_attempt_merchant', 191)->nullable();
            $table->text('payment_attempt_verification_key')->nullable();
            $table->char('payment_attempt_order_hash', 64)->nullable();
            $table->unsignedInteger('payment_attempt_reservation_version')->nullable();
            $table->string('payment_review_error', 500)->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();

            $table->unique(
                ['payment_attempt_provider', 'payment_attempt_reference'],
                self::LOOKUP_INDEX
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(self::LOOKUP_INDEX);
            $table->dropIndex(['payment_attempt_started_at']);
            $table->dropColumn([
                'payment_attempt_started_at',
                'payment_attempt_provider',
                'payment_attempt_reference',
                'payment_expected_amount_minor',
                'payment_expected_currency',
                'payment_attempt_environment',
                'payment_attempt_merchant',
                'payment_attempt_verification_key',
                'payment_attempt_order_hash',
                'payment_attempt_reservation_version',
                'payment_review_error',
                'confirmation_sent_at',
            ]);
        });
    }
}
