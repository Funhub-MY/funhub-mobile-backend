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
        Schema::create('merchant_offer_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('store_id')->nullable()->constrained();
            $table->smallInteger('status');
            $table->string('name');
            $table->string('sku')->nullable();

            $table->text('description');
            $table->text('fine_print');
            $table->text('redemption_policy');
            $table->text('cancellation_policy');

            $table->dateTime('publish_at')->nullable();

            $table->string('purchase_method');
            $table->double('unit_price');
            $table->double('discounted_point_fiat_price');
            $table->double('point_fiat_price');
            $table->double('discounted_fiat_price');
            $table->double('fiat_price');

            $table->double('expiry_days');

            $table->softDeletes();

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
        Schema::dropIfExists('merchant_offer_campaigns');
    }
};
