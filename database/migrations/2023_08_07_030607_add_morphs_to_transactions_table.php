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
            $table->unsignedInteger("transactionable_id")->after('transaction_no');
            $table->string("transactionable_type")->after('transaction_no');
            $table->index(["transactionable_id", "transactionable_type"]);
            
            $table->dropColumn('product_id');
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
            $table->dropIndex(["transactionable_id", "transactionable_type"]);
            $table->dropColumn("transactionable_id");
            $table->dropColumn("transactionable_type");
            $table->foreignId('product_id');
        });
    }
};
