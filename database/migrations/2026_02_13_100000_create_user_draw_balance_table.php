<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Current project (funhub-mobile-backend) table for mission draw balance.
 * Used when both projects share the same DB and v2 has dropped user_missions.
 * Tracks: product-purchase extra chances, mission completion draw_chance, lucky draw count.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_draw_balance')) {
            return;
        }

        Schema::create('user_draw_balance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->tinyInteger('mission_1')->default(0);
            $table->tinyInteger('mission_2')->default(0);
            $table->tinyInteger('mission_3')->default(0);
            $table->tinyInteger('mission_4')->default(0);
            $table->integer('cycle')->default(0);
            $table->unsignedInteger('draw_chance')->default(0)->comment('From completing all 4 missions');
            $table->unsignedInteger('extra_chance')->default(0)->comment('From product purchase');
            $table->unsignedInteger('total_drawn')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_draw_balance');
    }
};
