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
        Schema::table('merchant_offer_user', function (Blueprint $table) {
            $table->string('order_no')->after('user_id');
            $table->integer('quantity')->default(0)->after('user_id');
            $table->double('unit_price', 8, 2)->default(0)->after('user_id');
            $table->double('total', 8, 2)->default(0)->after('user_id');
            $table->double('tax', 8, 2)->default(0)->after('user_id');
            $table->double('discount', 8, 2)->default(0)->after('user_id');
            $table->double('net_amount', 8, 2)->default(0)->after('user_id');
            $table->text('remarks')->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_offer_user', function (Blueprint $table) {
            $table->dropColumn('order_no');
            $table->dropColumn('quantity');
            $table->dropColumn('unit_price');
            $table->dropColumn('total');
            $table->dropColumn('tax');
            $table->dropColumn('discount');
            $table->dropColumn('net_amount');
            $table->dropColumn('remarks');
        });
    }
};
