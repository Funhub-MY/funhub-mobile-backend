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
        Schema::table('missions_users', function (Blueprint $table) {
            $table->dropColumn('current_value');

            $table->json('current_values')->nullable()->after('user_id');
            $table->timestamp('last_rewarded_at')->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('missions_users', function (Blueprint $table) {
            //
        });
    }
};
