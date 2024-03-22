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
        Schema::create('merchant_offer_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->string('name');
            $table->boolean('is_featured')->default(0);
            $table->string('slug');
            $table->foreignId('cover_media_id')->nullable();
            $table->text('description')->nullable();
            $table->string('lang')->default('en');
            $table->foreignId('user_id');
            $table->timestamps();
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('merchant_offer_categories');
    }
};
