<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kdcc_teams')) {
            Schema::create('kdcc_teams', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->integer('category_id');
                $table->integer('vote_count')->default(0);
                $table->string('team_image_path')->nullable();
                $table->timestamps();
                
                $table->index('category_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kdcc_teams');
    }
};
