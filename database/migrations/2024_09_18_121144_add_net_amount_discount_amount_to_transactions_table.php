<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->float('discount_amount')->nullable()->after('amount');
            $table->float('net_amount')->nullable()->after('discount_amount');
            $table->boolean('using_point_discount')->default(false)->after('amount');
            $table->double('point_to_use')->nullable()->after('discount_amount');
            $table->double('point_balance_after_usage', 8, 2)->nullable()->after('amount');
            $table->foreignId('point_ledger_id')->nullable()->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
            $table->dropColumn('using_point_discount');
            $table->dropColumn('point_balance_after_usage');
            $table->dropColumn('point_ledger_id');
        });
    }
};
