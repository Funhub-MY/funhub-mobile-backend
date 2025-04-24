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
        Schema::create('failed_store_imports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('address_postcode')->nullable();
            $table->string('city')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('business_phone_no')->nullable();
            $table->json('business_hours')->nullable();
            $table->json('rest_hours')->nullable();
            $table->boolean('is_appointment_only')->default(false);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->string('google_place_id')->nullable();
            $table->decimal('lang', 10, 7)->nullable();
            $table->decimal('long', 10, 7)->nullable();
            $table->text('parent_categories')->nullable();
            $table->text('sub_categories')->nullable();
            $table->boolean('is_hq')->default(false);
            $table->text('failure_reason');
            $table->json('original_data')->nullable();
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
        Schema::dropIfExists('failed_store_imports');
    }
};
