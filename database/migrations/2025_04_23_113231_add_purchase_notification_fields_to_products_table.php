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
            $table->boolean('enable_purchase_notification')->default(false);
            $table->text('purchase_notification_en')->nullable();
            $table->text('purchase_notification_zh')->nullable();
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
            $table->dropColumn([
                'enable_purchase_notification',
                'purchase_notification_en',
                'purchase_notification_zh'
            ]);
        });
    }
};
