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
        Schema::table('campaigns_questions', function (Blueprint $table) {
            $table->string('answer_type')->nullable()->default('text')->after('question');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('campaigns_questions', function (Blueprint $table) {
            $table->dropColumn('answer_type');
        });
    }
};
