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
            $table->integer('per_user_limit_count')->nullable()->after('per_user_limit');
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
            $table->dropColumn('per_user_limit_count');
        });
    }
};
