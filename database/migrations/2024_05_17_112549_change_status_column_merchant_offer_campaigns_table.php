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
        Schema::table('merchant_offer_campaigns', function (Blueprint $table) {
            // make existing column status default to 0, use DB::statement to change the column type
            $table->boolean('status')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_offer_campaigns', function (Blueprint $table) {
            $table->boolean('status')->change();
        });
    }
};
