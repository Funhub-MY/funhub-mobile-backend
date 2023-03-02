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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_country_code')->nullable()->after('email_verified_at');
            $table->string('phone_no')->unique()->nullable()->after('email_verified_at');
            $table->timestamp('otp_verified_at')->default(false)->after('email_verified_at');
            $table->string('otp')->nullable()->after('email_verified_at');
            $table->timestamp('otp_expiry')->nullable()->after('email_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_country_code');
            $table->dropColumn('phone_no');
            $table->dropColumn('otp_verified');
            $table->dropColumn('otp_expiry');
            $table->dropColumn('otp');
        });
    }
};
