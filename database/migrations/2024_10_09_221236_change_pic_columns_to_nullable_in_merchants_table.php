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
            $table->string('pic_name')->nullable()->change();
            $table->string('pic_designation')->nullable()->change();
            $table->string('pic_ic_no')->nullable()->change();
            $table->string('pic_phone_no')->nullable()->change();
            $table->string('pic_email')->nullable()->change();
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
            $table->string('pic_name')->change();
            $table->string('pic_phone_no')->change();
            $table->string('pic_email')->change();
        });
    }
};
