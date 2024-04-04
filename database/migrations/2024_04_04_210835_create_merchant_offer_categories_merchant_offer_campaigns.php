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
        Schema::create('merchant_offer_categories_merchant_offer_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_offer_category_id');
            $table->foreignId('merchant_offer_campaign_id');
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
        Schema::dropIfExists('merchant_offer_categories_merchant_offer_campaigns');
    }
};
