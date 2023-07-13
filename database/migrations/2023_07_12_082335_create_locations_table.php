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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->morphs('locatable');
            $table->string('name');

            $table->text('location')->nullable();
            $table->tinyInteger('status')->default(1); // 0 = draft, 1 = published, 2 = archived

            $table->string('lat');
            $table->string('lng');

            $table->string('address');
            $table->string('address_2')->nullable();
            $table->string('zip_code');
            $table->string('city');
            $table->foreignId('state_id');
            $table->foreignId('country_id');
            $table->string('phone_no')->nullable();

            $table->double('average_ratings')->nullable();
            $table->foreignId('merchant_id')->nullable();
            $table->foreignId('user_id')->nullable();

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
        Schema::dropIfExists('locations');
    }
};
