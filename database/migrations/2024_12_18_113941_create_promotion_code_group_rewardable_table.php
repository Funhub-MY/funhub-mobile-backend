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
    public function up(): void
    {
        Schema::create('promotion_code_group_rewardable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_code_group_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('rewardable', 'pcg_rewardable');
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->unique(['promotion_code_group_id', 'rewardable_id', 'rewardable_type'], 'pcg_rewardable_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_code_group_rewardable');
    }
};
