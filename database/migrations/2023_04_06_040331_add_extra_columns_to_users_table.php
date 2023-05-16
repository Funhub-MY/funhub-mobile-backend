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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
            $table->text('bio')->nullable()->after('avatar');
            $table->date('dob')->nullable()->after('avatar');
            $table->string('gender')->nullable()->after('avatar');
            $table->foreignId('country_id')->nullable()->after('avatar');
            $table->foreignId('state_id')->nullable()->after('avatar');
            $table->string('job_title')->nullable()->after('avatar');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
            $table->dropColumn('bio');
            $table->dropColumn('dob');
            $table->dropColumn('gender');
            $table->dropColumn('country_id');
            $table->dropColumn('state_id');
            $table->dropColumn('job_title');
        });
    }
};
