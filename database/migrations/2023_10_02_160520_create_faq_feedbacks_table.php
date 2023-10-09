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
        Schema::create('faq_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_id');
            $table->foreignId('user_id');
            $table->tinyInteger('rating')->default(0); // 0 = not helpful, 1 = somewhat helpful, 2 = very helpful
            $table->text('comment')->nullable();
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
        Schema::dropIfExists('faq_feedbacks');
    }
};
