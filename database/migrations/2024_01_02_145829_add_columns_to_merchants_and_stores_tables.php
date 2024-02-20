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
        // Add columns to the merchants table
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('company_reg_no')->after('business_name')->nullable();
            $table->string('brand_name')->after('company_reg_no')->nullable();
            $table->string('pic_designation')->after('pic_name')->nullable();
            $table->string('pic_ic_no')->after('pic_designation')->nullable();
        });

        // Add columns to the stores table
        Schema::table('stores', function (Blueprint $table) {
            $table->string('manager_name')->after('name')->nullable();
            $table->string('manager_contact_no')->after('manager_name')->nullable();
            $table->json('business_hours')->after('business_phone_no')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reverse the changes made in the 'up' method
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['company_reg_no', 'brand_name', 'pic_designation', 'pic_ic_no']);
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['manager_name', 'manager_contact_no', 'business_hours']);
        });
    }
};
