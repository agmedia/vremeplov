<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePaymentProviderReferencesTable extends Migration
{
    private const UNIQUE_INDEX = 'payment_provider_reference_unique';

    public function up()
    {
        Schema::create('payment_provider_references', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('provider', 32);
            $table->string('reference', 191);
            $table->unsignedBigInteger('order_id');
            $table->timestamps();

            $table->unique(['provider', 'reference'], self::UNIQUE_INDEX);
            $table->index('order_id');
        });

        // Historical PayPal rows contain PayerID rather than txn_id, so only
        // WSPay references can be safely adopted. Earliest row wins if a live
        // database unexpectedly contains the same reference on two orders.
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
                $row = DB::table('order_transactions')
                    ->where('id', $payment->canonical_id)
                    ->first(['order_id', 'created_at', 'updated_at']);

                if ($row) {
                    DB::table('payment_provider_references')->insertOrIgnore([
                        'provider' => 'wspay',
                        'reference' => (string) $payment->provider_reference,
                        'order_id' => (int) $row->order_id,
                        'created_at' => $row->created_at ?: now(),
                        'updated_at' => $row->updated_at ?: now(),
                    ]);
                }
            });
    }

    public function down()
    {
        Schema::dropIfExists('payment_provider_references');
    }
}
