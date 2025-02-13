<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesForUserStatistics extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('store_ratings', function (Blueprint $table) {
            // for the store ratings count query that filters by user_id and unique store counts
            $table->index(['user_id', 'store_id'], 'idx_store_ratings_user_store');
        });

        Schema::table('merchant_offer_user', function (Blueprint $table) {
            // for voucher purchases query that filters by user_id
            $table->index(['user_id'], 'idx_merchant_offer_user_user');
        });

        Schema::table('merchant_offer_claims_redemptions', function (Blueprint $table) {
            // for redemptions query filtering by user_id
            $table->index(['user_id'], 'idx_redemptions_user');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // for funcard purchases query that filters by user_id
            $table->index(['user_id'], 'idx_transactions_user');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('store_ratings', function (Blueprint $table) {
            $table->dropIndex('idx_store_ratings_user_store');
        });

        Schema::table('merchant_offer_user', function (Blueprint $table) {
            $table->dropIndex('idx_merchant_offer_user_user');
        });

        Schema::table('merchant_offer_claims_redemptions', function (Blueprint $table) {
            $table->dropIndex('idx_redemptions_user');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_user');
        });
    }
}
