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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_luckydraw_enabled')->default(false)->after('purchase_notification_url');
            $table->unsignedInteger('luckydraw_chance')->default(1)->after('is_luckydraw_enabled')
                ->comment('Number of draw chances awarded when product is purchased');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_luckydraw_enabled', 'luckydraw_chance']);
        });
    }
};
