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
            $table->decimal('fiat_price', 8,2)->change();
            $table->decimal('discounted_fiat_price', 8,2)->change();
            $table->decimal('point_fiat_price', 8,2)->change();
            $table->decimal('discounted_point_fiat_price', 8,2)->change();
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
            $table->double('fiat_price')->change();
            $table->double('discounted_fiat_price')->change();
            $table->double('point_fiat_price')->change();
            $table->double('discounted_point_fiat_price')->change();
        });
    }
};
