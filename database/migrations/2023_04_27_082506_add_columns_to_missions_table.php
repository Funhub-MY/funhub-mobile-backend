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
        Schema::table('missions', function (Blueprint $table) {
            $table->boolean('enabled')->default(false)->after('reward_quantity');
            $table->timestamp('enabled_at')->nullable()->after('reward_quantity');

            // repetable mission?
            $table->boolean('repetable')->default(false)->after('reward_quantity');

            // mission type
            $table->string('type')->default('default')->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('missions', function (Blueprint $table) {
                $table->dropColumn('enabled');
                $table->dropColumn('enabled_at');
                $table->dropColumn('repetable');
                $table->dropColumn('type');
        });
    }
};
