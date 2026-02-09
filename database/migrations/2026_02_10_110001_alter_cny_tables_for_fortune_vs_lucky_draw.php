<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // cny_merchandise_wins: link to lucky draw only (drop fortune_pick, add lucky_draw)
        Schema::table('cny_merchandise_wins', function (Blueprint $table) {
            $table->dropForeign(['cny_fortune_pick_id']);
            $table->dropColumn('cny_fortune_pick_id');
        });
        Schema::table('cny_merchandise_wins', function (Blueprint $table) {
            $table->foreignId('cny_lucky_draw_id')->nullable()->after('cny_merchandise_id')->constrained('cny_lucky_draws')->nullOnDelete();
        });

        // cny_fortune_picks: only fortune reward (nothing | promo_49 | promo_50)
        Schema::table('cny_fortune_picks', function (Blueprint $table) {
            $table->dropForeign(['reward_id']);
            $table->dropForeign(['cny_merchandise_id']);
            $table->dropColumn(['reward_id', 'cny_merchandise_id']);
        });
        Schema::table('cny_fortune_picks', function (Blueprint $table) {
            $table->renameColumn('reward_type', 'fortune_reward_type');
        });
    }

    public function down(): void
    {
        Schema::table('cny_fortune_picks', function (Blueprint $table) {
            $table->renameColumn('fortune_reward_type', 'reward_type');
        });
        Schema::table('cny_fortune_picks', function (Blueprint $table) {
            $table->unsignedBigInteger('reward_id')->nullable()->after('promotion_code_id');
            $table->unsignedBigInteger('cny_merchandise_id')->nullable()->after('reward_id');
            $table->foreign('reward_id')->references('id')->on('rewards')->nullOnDelete();
            $table->foreign('cny_merchandise_id')->references('id')->on('cny_merchandise')->nullOnDelete();
        });

        Schema::table('cny_merchandise_wins', function (Blueprint $table) {
            $table->dropForeign(['cny_lucky_draw_id']);
            $table->dropColumn('cny_lucky_draw_id');
        });
        Schema::table('cny_merchandise_wins', function (Blueprint $table) {
            $table->foreignId('cny_fortune_pick_id')->after('cny_merchandise_id')->constrained('cny_fortune_picks')->cascadeOnDelete();
        });
    }
};
