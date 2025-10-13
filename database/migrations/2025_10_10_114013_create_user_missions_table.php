<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasTable('user_missions')) {
            Schema::create('user_missions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->tinyInteger('mission_1')->default(0);
                $table->tinyInteger('mission_2')->default(0);
                $table->tinyInteger('mission_3')->default(0);
                $table->tinyInteger('mission_4')->default(0);
                $table->tinyInteger('mission_5')->default(0);
                $table->tinyInteger('mission_6')->default(0);
                $table->integer('cycle')->default(0);
                $table->integer('total_chance')->default(0);
                $table->integer('total_drawn')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('user_missions');
    }
};
