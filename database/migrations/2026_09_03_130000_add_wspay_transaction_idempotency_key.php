<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddWspayTransactionIdempotencyKey extends Migration
{
    private const UNIQUE_INDEX = 'order_transactions_order_idempotency_unique';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_transactions', function (Blueprint $table) {
            $table->string('provider_event', 32)->nullable()->after('payment_partner');
            $table->char('idempotency_key', 64)->nullable()->after('pg_order_id');
        });

        // Keep every legacy audit row. One canonical row for each known WSPay
        // payment gets the deterministic key so a delayed replay updates it
        // instead of creating yet another duplicate.
        DB::table('order_transactions as transactions')
          ->join('orders', 'orders.id', '=', 'transactions.order_id')
          ->where('orders.payment_code', 'wspay')
          ->whereNotNull('transactions.pg_order_id')
          ->whereRaw("TRIM(transactions.pg_order_id) <> ''")
          ->selectRaw(
              'TRIM(transactions.pg_order_id) AS provider_reference, '
              . 'MIN(transactions.id) AS canonical_id'
          )
          ->groupByRaw('TRIM(transactions.pg_order_id)')
          ->orderBy('canonical_id')
          ->get()
          ->each(function ($payment) {
              DB::table('order_transactions')
                ->where('id', $payment->canonical_id)
                ->update([
                    'idempotency_key' => $this->providerKey((string) $payment->provider_reference),
                ]);
          });

        Schema::table('order_transactions', function (Blueprint $table) {
            $table->unique('idempotency_key', self::UNIQUE_INDEX);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_transactions', function (Blueprint $table) {
            $table->dropUnique(self::UNIQUE_INDEX);
            $table->dropColumn(['provider_event', 'idempotency_key']);
        });
    }

    private function providerKey(string $provider_order_id): string
    {
        return hash('sha256', implode('|', [
            'wspay',
            'provider',
            trim($provider_order_id),
        ]));
    }
}
