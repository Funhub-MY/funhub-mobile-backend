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
        Schema::table('merchant_offer_vouchers', function (Blueprint $table) {
            $table->string('imported_code')->nullable()->after('code');
            $table->index('imported_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_offer_vouchers', function (Blueprint $table) {
            $table->dropIndex(['imported_code']);
            $table->dropColumn('imported_code');
        });
    }
};
