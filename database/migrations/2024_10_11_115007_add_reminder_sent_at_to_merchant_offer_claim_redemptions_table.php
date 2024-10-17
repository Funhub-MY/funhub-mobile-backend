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
        Schema::table('merchant_offer_claims_redemptions', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_offer_claims_redemptions', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
