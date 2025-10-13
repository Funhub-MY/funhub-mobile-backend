<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'rsvp')) {
                $table->boolean('rsvp')
                      ->default(false)
                      ->after('newbie_missions_completed_at');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'rsvp')) {
                $table->dropColumn('rsvp');
            }
        });
    }
};

