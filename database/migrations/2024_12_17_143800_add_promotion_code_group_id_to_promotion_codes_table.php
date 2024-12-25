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
        Schema::table('promotion_codes', function (Blueprint $table) {
            $table->foreignId('promotion_code_group_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('status')->default(true)->after('code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('promotion_codes', function (Blueprint $table) {
            $table->dropForeign(['promotion_code_group_id']);
            $table->dropColumn(['promotion_code_group_id', 'status']);
        });
    }
};
