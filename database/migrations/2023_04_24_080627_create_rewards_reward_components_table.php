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
        Schema::create('rewards_reward_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reward_id')->constrained();
            $table->foreignId('reward_component_id')->constrained();
            $table->double('points'); // points to make up 1 reward
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
        Schema::dropIfExists('rewards_reward_components');
    }
};
