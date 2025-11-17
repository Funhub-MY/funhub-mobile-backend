<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * NOTE: This migration should be run AFTER the data migration has populated merchant_id
     * for all existing records. Only make merchant_id NOT NULL if all records have been populated.
     */
    public function up(): void
    {
        // Check if all records have merchant_id populated
        $campaignsWithoutMerchant = DB::table('merchant_offer_campaigns')
            ->whereNull('merchant_id')
            ->count();
        
        $offersWithoutMerchant = DB::table('merchant_offers')
            ->whereNull('merchant_id')
            ->count();
        
        if ($campaignsWithoutMerchant > 0 || $offersWithoutMerchant > 0) {
            \Log::warning('[MakeMerchantIdNotNullable] Cannot make merchant_id NOT NULL - some records still have NULL', [
                'campaigns_without_merchant' => $campaignsWithoutMerchant,
                'offers_without_merchant' => $offersWithoutMerchant,
            ]);
            
            // Don't make NOT NULL if there are records without merchant_id
            // This allows the migration to run safely even if some records can't be populated
            return;
        }
        
        // Make merchant_id NOT NULL for merchant_offer_campaigns
        Schema::table('merchant_offer_campaigns', function (Blueprint $table) {
            $table->foreignId('merchant_id')->nullable(false)->change();
        });
        
        // Make merchant_id NOT NULL for merchant_offers
        Schema::table('merchant_offers', function (Blueprint $table) {
            $table->foreignId('merchant_id')->nullable(false)->change();
        });
        
        \Log::info('[MakeMerchantIdNotNullable] Successfully made merchant_id NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_offer_campaigns', function (Blueprint $table) {
            $table->foreignId('merchant_id')->nullable()->change();
        });
        
        Schema::table('merchant_offers', function (Blueprint $table) {
            $table->foreignId('merchant_id')->nullable()->change();
        });
    }
};
