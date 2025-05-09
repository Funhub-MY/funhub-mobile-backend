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
        Schema::table('promotion_code_groups', function (Blueprint $table) {
            $table->boolean('use_fix_amount_discount')->after('status')->default(false);
            $table->decimal('discount_amount', 10, 2)->after('use_fix_amount_discount')->nullable();
            $table->string('user_type')->after('discount_amount')->default('all');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('promotion_code_groups', function (Blueprint $table) {
            $table->dropColumn([
                'use_fix_amount_discount',
                'discount_amount',
                'user_type'
            ]);
        });
    }
};
