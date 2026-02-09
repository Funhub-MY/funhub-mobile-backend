<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cny_lucky_draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('drawn_at');
            $table->string('reward_type', 32)->nullable(); // promo_code, merchandise, nothing
            $table->unsignedBigInteger('promotion_code_id')->nullable();
            $table->unsignedBigInteger('cny_merchandise_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'drawn_at']);
            $table->foreign('promotion_code_id')->references('id')->on('promotion_codes')->nullOnDelete();
            $table->foreign('cny_merchandise_id')->references('id')->on('cny_merchandise')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cny_lucky_draws');
    }
};
