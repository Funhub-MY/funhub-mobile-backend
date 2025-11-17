<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferWhitelist;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration migrates the hardcoded merchant user whitelist to the database.
     * It finds all offers from the whitelisted merchants and creates whitelist entries
     * with override_days = null (fully whitelisted).
     */
    public function up(): void
    {
        // Hardcoded merchant user IDs from the controller
        $merchantUserWhitelist = [28825,93716,94359,94361,94377,94515,94516,94519,94520,94521,94522,94552,94557,94561,94588,94589,94590,94591,94592,93761,94739,95133,95174,96126,96135,96142,96151,96870];
        
        // Find all offers from these merchants
        $offers = MerchantOffer::whereIn('user_id', $merchantUserWhitelist)
            ->get();
        
        $whitelistEntries = [];
        $now = now();
        
        foreach ($offers as $offer) {
            // Check if whitelist entry already exists
            $exists = MerchantOfferWhitelist::where('merchant_offer_id', $offer->id)->exists();
            
            if (!$exists) {
                $whitelistEntries[] = [
                    'merchant_offer_id' => $offer->id,
                    'merchant_user_id' => $offer->user_id,
                    'override_days' => null, // Fully whitelisted (no restriction)
                    'notes' => 'Migrated from hardcoded whitelist',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        
        // Bulk insert whitelist entries
        if (!empty($whitelistEntries)) {
            // Insert in chunks to avoid memory issues
            $chunks = array_chunk($whitelistEntries, 500);
            foreach ($chunks as $chunk) {
                DB::table('merchant_offer_whitelists')->insert($chunk);
            }
            
            \Log::info('[MigrateHardcodedWhitelist] Migrated whitelist entries', [
                'total_offers' => $offers->count(),
                'whitelist_entries_created' => count($whitelistEntries),
                'merchant_user_ids' => $merchantUserWhitelist,
            ]);
        } else {
            \Log::info('[MigrateHardcodedWhitelist] No new whitelist entries to create');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove whitelist entries that were migrated (identified by notes)
        MerchantOfferWhitelist::where('notes', 'Migrated from hardcoded whitelist')
            ->delete();
    }
};
