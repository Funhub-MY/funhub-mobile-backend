<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cny_fortune_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('picked_at');
            $table->string('fortune_category', 32); // peace, career, health
            $table->string('fortune_title');
            $table->text('fortune_description')->nullable();
            // Lucky draw result
            $table->string('reward_type', 32)->nullable(); // promo_code, reward_funbox, merchandise, none
            $table->unsignedBigInteger('promotion_code_id')->nullable();
            $table->unsignedBigInteger('reward_id')->nullable();
            $table->unsignedBigInteger('cny_merchandise_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'picked_at']);
            $table->foreign('promotion_code_id')->references('id')->on('promotion_codes')->nullOnDelete();
            $table->foreign('reward_id')->references('id')->on('rewards')->nullOnDelete();
            $table->foreign('cny_merchandise_id')->references('id')->on('cny_merchandise')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cny_fortune_picks');
    }
};
