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
        Schema::create('articles_hidden_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('hide_until')->nullable();
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
        Schema::dropIfExists('articles_hidden_users');
    }
};
