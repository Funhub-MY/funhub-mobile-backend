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
        Schema::table('promotion_code_groups', function (Blueprint $table) {
            $table->string('prefix')->nullable()->after('name');
            $table->string('suffix')->nullable()->after('prefix');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('promotion_code_groups', function (Blueprint $table) {
            $table->dropColumn(['prefix', 'suffix']);
        });
    }
};
