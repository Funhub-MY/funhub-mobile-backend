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
        Schema::table('merchant_offers', function (Blueprint $table) {
            $table->double('fiat_price')->after('unit_price');
            $table->double('discounted_fiat_price')->nullable()->after('unit_price');
            $table->double('point_fiat_price')->nullable()->after('unit_price');
            $table->double('discounted_point_fiat_price')->nullable()->after('unit_price');
            $table->string('purchase_method')->default('points')->after('discounted_point_fiat_price'); // points/fiat
            $table->string('currency')->nullable()->after('unit_price');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_offers', function (Blueprint $table) {
            $table->dropColumn('fiat_price');
            $table->dropColumn('discounted_fiat_price');
            $table->dropColumn('point_fiat_price');
            $table->dropColumn('discounted_point_fiat_price');
            $table->dropColumn('purchase_method');
            $table->dropColumn('currency');
        });
    }
};
