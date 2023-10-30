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
        Schema::table('locations', function (Blueprint $table) {
            $table->mediumInteger('lat_1000_floor')->storedAs('FLOOR(lat * 1000)');
            // remove old index first
            $table->dropIndex('lat_lng_locations_lat_lng_index');
            $table->index(['lat_1000_floor', 'lng'], 'lat_1000_floor_lng_locations_lat_lng_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex('lat_1000_floor_lng_locations_lat_lng_index');
            $table->dropColumn('lat_1000_floor');
            $table->index(['lat', 'lng'], 'lat_lng_locations_lat_lng_index');
        });
    }
};
