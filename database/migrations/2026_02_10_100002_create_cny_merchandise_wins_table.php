<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cny_merchandise_wins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cny_merchandise_id')->constrained('cny_merchandise')->cascadeOnDelete();
            $table->foreignId('cny_fortune_pick_id')->constrained('cny_fortune_picks')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'cny_merchandise_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cny_merchandise_wins');
    }
};
