<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rsvp_users', function (Blueprint $table) {
            if (!Schema::hasColumn('rsvp_users', 'dob')) {
                $table->date('dob')->nullable()->after('phone_no');
            }

            if (!Schema::hasColumn('rsvp_users', 'gender')) {
                $table->string('gender', 20)->nullable()->after('dob');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rsvp_users', function (Blueprint $table) {

            if (Schema::hasColumn('rsvp_users', 'dob')) {
                $table->dropColumn('dob');
            }

            if (Schema::hasColumn('rsvp_users', 'gender')) {
                $table->dropColumn('gender');
            }
        });
    }
};

