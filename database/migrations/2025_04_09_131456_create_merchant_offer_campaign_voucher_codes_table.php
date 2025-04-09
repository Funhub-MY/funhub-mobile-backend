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
        Schema::create('merchant_offer_campaign_voucher_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_offer_campaign_id')->constrained()->onDelete('cascade');
            $table->foreignId('voucher_id')->nullable()->constrained('merchant_offer_vouchers')->onDelete('set null');
            $table->string('code');
            $table->boolean('is_used')->default(false);
            $table->timestamps();
            
            $table->index('merchant_offer_campaign_id');
            $table->index('voucher_id');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('merchant_offer_campaign_voucher_codes');
    }
};
