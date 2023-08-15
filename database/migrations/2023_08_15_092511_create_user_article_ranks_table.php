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
        Schema::create('user_article_ranks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('article_id');
            $table->double('affinity', 8, 2)->default(0);
            $table->double('weight', 8, 2)->default(0);
            $table->double('score', 8, 2)->default(0);
            $table->timestamp('last_built')->nullable();
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
        Schema::dropIfExists('user_article_ranks');
    }
};
