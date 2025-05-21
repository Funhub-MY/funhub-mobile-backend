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
        Schema::create('promotion_code_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('promotion_code_id')->constrained()->onDelete('cascade');
            $table->integer('usage_count')->default(1);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            
            // Add a unique constraint to prevent duplicate entries
            $table->unique(['user_id', 'promotion_code_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('promotion_code_user');
    }
};
