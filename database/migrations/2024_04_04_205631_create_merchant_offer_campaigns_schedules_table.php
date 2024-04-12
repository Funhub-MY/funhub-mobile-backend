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
        Schema::create('merchant_offer_campaigns_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_offer_campaign_id');

            $table->dateTime('available_at');
            $table->dateTime('available_until');
            $table->integer('quantity');

            $table->boolean('flash_deal')->default(false);
            $table->integer('expiry_days')->nullable();

            $table->foreignId('user_id')->constrained();
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
        Schema::dropIfExists('merchant_offer_campaigns_schedules');
    }
};
