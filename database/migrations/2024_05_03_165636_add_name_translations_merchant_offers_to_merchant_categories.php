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
        Schema::table('merchant_categories', function (Blueprint $table) {
            $table->json('name_translation')->nullable()->after('name');
            //parent_id
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_categories', function (Blueprint $table) {
            $table->dropColumn('name_translation');
            $table->dropColumn('parent_id');
        });
    }
};
