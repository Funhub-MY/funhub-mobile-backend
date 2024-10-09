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
        Schema::table('merchants', function (Blueprint $table) {
            // authorised personnel designation, name, ic_no
            $table->string('authorised_personnel_designation')->nullable()->after('pic_email');
            $table->string('authorised_personnel_name')->nullable()->after('authorised_personnel_designation');
            $table->string('authorised_personnel_ic_no')->nullable()->after('authorised_personnel_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('authorised_personnel_designation');
            $table->dropColumn('authorised_personnel_name');
            $table->dropColumn('authorised_personnel_ic_no');
        });
    }
};
