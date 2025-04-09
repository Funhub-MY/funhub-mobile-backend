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
            $table->foreignId('merchant_offer_campaign_id');
            $table->foreign('merchant_offer_campaign_id', 'mocc_campaign_id_foreign')
                ->references('id')
                ->on('merchant_offer_campaigns')
                ->onDelete('cascade');
            $table->foreignId('voucher_id')->nullable();
            $table->foreign('voucher_id', 'mocc_voucher_id_foreign')
                ->references('id')
                ->on('merchant_offer_vouchers')
                ->onDelete('set null');
            $table->string('code');
            $table->boolean('is_used')->default(false);
            $table->timestamps();
            
            $table->index('merchant_offer_campaign_id', 'mocc_campaign_id_idx');
            $table->index('voucher_id', 'mocc_voucher_id_idx');
            $table->index('code', 'mocc_code_idx');
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
