<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOffer;
use App\Models\User;
use App\Models\Merchant;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Populate merchant_id in merchant_offer_campaigns from user_id
        DB::statement('
            UPDATE merchant_offer_campaigns moc
            INNER JOIN users u ON moc.user_id = u.id
            INNER JOIN merchants m ON u.id = m.user_id
            SET moc.merchant_id = m.id
            WHERE moc.merchant_id IS NULL
        ');

        // Populate merchant_id in merchant_offers from campaign or user_id
        // First, try to get from campaign
        DB::statement('
            UPDATE merchant_offers mo
            INNER JOIN merchant_offer_campaigns moc ON mo.merchant_offer_campaign_id = moc.id
            SET mo.merchant_id = moc.merchant_id
            WHERE mo.merchant_id IS NULL 
            AND mo.merchant_offer_campaign_id IS NOT NULL
            AND moc.merchant_id IS NOT NULL
        ');

        // Then, populate from user_id for offers without campaigns
        DB::statement('
            UPDATE merchant_offers mo
            INNER JOIN users u ON mo.user_id = u.id
            INNER JOIN merchants m ON u.id = m.user_id
            SET mo.merchant_id = m.id
            WHERE mo.merchant_id IS NULL
        ');

        // Log results
        $campaignsUpdated = DB::table('merchant_offer_campaigns')
            ->whereNotNull('merchant_id')
            ->count();
        
        $offersUpdated = DB::table('merchant_offers')
            ->whereNotNull('merchant_id')
            ->count();

        \Log::info('[PopulateMerchantId] Migration completed', [
            'campaigns_updated' => $campaignsUpdated,
            'offers_updated' => $offersUpdated,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Set merchant_id to NULL (can't fully reverse, but can clear)
        DB::table('merchant_offer_campaigns')->update(['merchant_id' => null]);
        DB::table('merchant_offers')->update(['merchant_id' => null]);
    }
};
