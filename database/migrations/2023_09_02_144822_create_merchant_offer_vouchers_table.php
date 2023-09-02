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
        Schema::create('merchant_offer_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_offer_id')->constrained()->onDelete('cascade');
            $table->foreignId('owned_by_id')->nullable(); // only set when voucher is claimed
            $table->string('code');
            $table->boolean('voided')->default(false);
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
        Schema::dropIfExists('merchant_offer_vouchers');
    }
};
