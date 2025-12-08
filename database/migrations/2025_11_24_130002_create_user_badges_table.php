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
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('badge_id')->constrained()->onDelete('cascade');
            $table->timestamp('earned_at')->useCurrent();
            $table->foreignId('reservation_id')->nullable()->constrained()->onDelete('set null');
            $table->unsignedInteger('progress_value')->nullable()->comment('Progress value when badge was earned');
            $table->json('metadata')->nullable()->comment('Additional data about badge earning');
            $table->boolean('is_active')->default(false)->comment('Whether this badge is displayed on user profile as showcase/preferred badge');

            $table->unique(['user_id', 'badge_id'], 'unique_user_badge');
            $table->index('user_id');
            $table->index('badge_id');
            $table->index('earned_at');
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_badges');
    }
};

