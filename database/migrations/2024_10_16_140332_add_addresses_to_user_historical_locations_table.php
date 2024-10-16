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
        Schema::table('user_historical_locations', function (Blueprint $table) {
            $table->string('google_id')->nullable()->after('lng');
            $table->string('address')->nullable()->after('lng');
            $table->string('address_2')->nullable()->after('lng');
            $table->string('zip_code')->nullable()->after('lng');
            $table->string('city')->nullable()->after('lng');
            $table->string('state')->nullable()->after('lng');
            $table->string('country')->nullable()->after('lng');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_historical_locations', function (Blueprint $table) {
            $table->dropColumn('google_id');
            $table->dropColumn('address');
            $table->dropColumn('address_2');
            $table->dropColumn('zip_code');
            $table->dropColumn('city');
            $table->dropColumn('state');
            $table->dropColumn('country');
        });
    }
};
