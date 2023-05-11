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
        Schema::table('reward_components', function (Blueprint $table) {
            $table->dropForeign(['reward_id']);
            $table->dropColumn('reward_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('reward_components', function (Blueprint $table) {
            $table->foreignId('reward_id')->constrained();
        });
    }
};
