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
        Schema::table('search_keywords', function (Blueprint $table) {
            // language
            $table->string('lang')->after('keyword')->default('zh');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('search_keywords', function (Blueprint $table) {
            $table->dropColumn('lang');
        });
    }
};
