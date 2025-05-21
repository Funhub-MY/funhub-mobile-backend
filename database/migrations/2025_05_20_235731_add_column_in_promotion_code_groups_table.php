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
            $table->string('code_type')->after('discount_amount')->nullable();
            $table->string('discount_type')->after('code_type')->nullable();
            $table->double('min_spend_amount', 8, 2)->after('discount_type')->default(0);
            $table->integer('per_user_limit')->after('min_spend_amount')->default(0);
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
            $table->dropColumn(['code_type', 'discount_type', 'min_spend_amount', 'per_user_limit']);
        });
    }
};
