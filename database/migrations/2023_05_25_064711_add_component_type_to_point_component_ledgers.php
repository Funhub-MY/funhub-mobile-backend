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
        Schema::table('point_component_ledgers', function (Blueprint $table) {
            $table->string('component_type')->nullable()->after('pointable_id');
            $table->string('component_id')->nullable()->after('component_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('point_component_ledgers', function (Blueprint $table) {
            $table->dropColumn('component_type');
            $table->dropColumn('component_id');
        });
    }
};
