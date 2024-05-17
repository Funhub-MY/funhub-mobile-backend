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
        Schema::table('merchant_offer_campaigns_schedules', function (Blueprint $table) {
            $table->boolean('status')->default(0)->after('id');
            $table->timestamp('publish_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_offer_campaigns_schedules', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropColumn('publish_at');
        });
    }
};
