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
        Schema::table('view_queues', function (Blueprint $table) {
            $table->integer('updated_scheduled_views')->after('scheduled_views')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('view_queues', function (Blueprint $table) {
            $table->dropColumn('updated_scheduled_views');
        });
    }
};
