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
        Schema::create('autocomplete_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('suggestion');
            $table->string('city_name');
            $table->string('city_standardised_name');
            $table->unsignedBigInteger('city_id');
            $table->string('keyword');
            $table->unsignedBigInteger('keyword_id');
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
        Schema::dropIfExists('autocomplete_suggestions');
    }
};
