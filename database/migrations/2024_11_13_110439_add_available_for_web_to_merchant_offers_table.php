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
        Schema::table('merchant_offers', function (Blueprint $table) {
            $table->boolean('available_for_web')->default(false)->after('available_until');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_offers', function (Blueprint $table) {
            $table->dropColumn('available_for_web');
        });
    }
};
