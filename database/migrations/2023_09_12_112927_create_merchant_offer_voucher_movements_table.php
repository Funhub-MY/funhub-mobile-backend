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
        Schema::create('merchant_offer_voucher_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_merchant_offer_id');
            $table->foreignId('to_merchant_offer_id');
            $table->foreignId('voucher_id');
            $table->foreignId('user_id');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('merchant_offer_voucher_movements');
    }
};
