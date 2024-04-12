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
            // drop event and value column
            $table->dropColumn('event');
            $table->dropColumn('value');

            $table->json('events')->after('description'); // array so they can chain one after another
            $table->json('values')->after('description');
            $table->string('frequency')->default('one-off')->after('description');
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
            $table->string('event')->after('description');
            $table->string('value')->after('description');

            $table->dropColumn('events');
            $table->dropColumn('values');
            $table->dropColumn('frequency');
        });
    }
};
