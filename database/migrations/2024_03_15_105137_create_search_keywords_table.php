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
        Schema::create('search_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword');
            $table->text('description')->nullable();
            $table->bigInteger('hits')->default(0);
            $table->boolean('blacklisted')->default(false);
            $table->timestamp('sponsored_from')->nullable();
            $table->timestamp('sponsored_to')->nullable();
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
        Schema::dropIfExists('search_keywords');
    }
};
