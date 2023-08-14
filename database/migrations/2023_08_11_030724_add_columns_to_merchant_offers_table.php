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
            $table->text('fine_print')->nullable()->after('description');
            $table->text('redemption_policy')->nullable()->after('description');
            $table->text('cancellation_policy')->nullable()->after('description');
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
            $table->dropColumn('fine_print');
            $table->dropColumn('redemption_policy');
            $table->dropColumn('cancellation_policy');
        });
    }
};
