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
        if (!Schema::hasTable('kdcc_votes')) {
            Schema::create('kdcc_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained();
                $table->integer('category_id');
                $table->foreignId('team_id')->constrained('kdcc_teams');
                $table->timestamps();
                
                // Ensure one vote per user per category
                $table->unique(['user_id', 'category_id']);
                $table->index('category_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kdcc_votes');
    }
};
