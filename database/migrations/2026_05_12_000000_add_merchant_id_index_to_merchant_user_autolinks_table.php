<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * merchant_id is used in EXISTS / whereHas from merchants; without an index
     * the subquery scans the full autolinks table per row.
     */
    public function up(): void
    {
        Schema::table('merchant_user_autolinks', function (Blueprint $table) {
            $table->index('merchant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_user_autolinks', function (Blueprint $table) {
            $table->dropIndex(['merchant_id']);
        });
    }
};
