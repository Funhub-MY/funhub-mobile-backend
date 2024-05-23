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
            $table->json('name_translations')->nullable()->after('name');
            $table->json('description_translations')->nullable()->after('description');
            $table->json('fine_print_translations')->nullable()->after('fine_print');
            $table->json('cancellation_policy_translations')->nullable()->after('cancellation_policy');
            $table->json('redemption_policy_translations')->nullable()->after('redemption_policy');
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
            $table->dropColumn('name_translations');
            $table->dropColumn('description_translations');
            $table->dropColumn('fine_print_translations');
            $table->dropColumn('redemption_policy_translations');
        });
    }
};
