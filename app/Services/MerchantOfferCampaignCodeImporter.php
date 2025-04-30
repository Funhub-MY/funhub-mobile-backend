<?php

namespace App\Services;

use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferCampaignVoucherCode;
use App\Models\MerchantOfferVoucher; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MerchantOfferCampaignCodeImporter
{    
    /**
    * Imports voucher codes and assigns them efficiently to existing MerchantOfferVoucher records
    * associated with the campaign that do not yet have a code, using a single batch update.
    *
    * @param MerchantOfferCampaign $merchantOfferCampaign The campaign to import codes for.
    * @param array $codes An array of unique voucher codes to import.
    * @throws \Exception If the number of codes does not match the number of empty vouchers or on DB error.
    * @throws \InvalidArgumentException If no unique codes are provided.
    */
   public function importCodes(MerchantOfferCampaign $merchantOfferCampaign, array $codes): void
   {
       // Ensure codes are unique within the input array and filter out empty/null values
       $uniqueCodes = array_values(array_unique(array_filter(array_map('trim', $codes))));
       $importCount = count($uniqueCodes);

       if ($importCount === 0) {
           Log::warning('[Voucher Code Import] No unique, non-empty codes provided for import.', ['campaign_id' => $merchantOfferCampaign->id]);
           throw new \InvalidArgumentException('No unique, non-empty codes provided for import.');
       }

       Log::info("[Voucher Code Import] Attempting to import {$importCount} unique codes for Campaign ID: {$merchantOfferCampaign->id}");

       DB::beginTransaction();

       try {
           // Find existing MerchantOfferVoucher IDs for this campaign's offers that need a code
           // Ensure we only fetch exactly the number needed and lock them.
           $voucherIdsToUpdate = MerchantOfferVoucher::query()
               ->join('merchant_offers', 'merchant_offer_vouchers.merchant_offer_id', '=', 'merchant_offers.id')
               ->where('merchant_offers.merchant_offer_campaign_id', $merchantOfferCampaign->id)
               ->whereNull('merchant_offer_vouchers.imported_code') // Find vouchers without an imported code
               ->whereNull('merchant_offer_vouchers.owned_by_id')   // Ensure not already purchased/owned
               ->orderBy('merchant_offer_vouchers.id') // Consistent ordering is important
               ->limit($importCount) // Fetch only as many as we have codes for
               ->lockForUpdate() // Lock rows to prevent race conditions during update
               ->pluck('merchant_offer_vouchers.id') // Get only the IDs
               ->all(); // Convert collection to array

           $emptyVoucherCount = count($voucherIdsToUpdate);

           // Validate counts - IMPORTANT: We need a code for each available empty voucher slot
           if ($importCount !== $emptyVoucherCount) {
                DB::rollBack(); // Rollback before throwing
                Log::error("[Voucher Code Import] Quantity Mismatch: Provided codes ({$importCount}) != available voucher slots ({$emptyVoucherCount}).", ['campaign_id' => $merchantOfferCampaign->id]);
                throw new \Exception("Quantity Mismatch: The number of imported codes ({$importCount}) does not match the number of available empty voucher slots ({$emptyVoucherCount}) for this campaign.");
           }

           if ($emptyVoucherCount === 0) {
               DB::commit(); // Nothing to update, commit transaction
               Log::info('[Voucher Code Import] No empty voucher slots found needing codes for this campaign.', ['campaign_id' => $merchantOfferCampaign->id]);
               return; // Exit early
           }

           // Prepare data for batch update using CASE WHEN
           $cases = [];
           $bindings = [];
           $idsString = implode(',', $voucherIdsToUpdate);

           foreach ($voucherIdsToUpdate as $index => $voucherId) {
               if (isset($uniqueCodes[$index])) {
                   $cases[] = "WHEN id = ? THEN ?";
                   $bindings[] = $voucherId;
                   $bindings[] = $uniqueCodes[$index]; // Assign the code
               }
           }

           // Corrected: Add the updated_at timestamp binding *before* the WHERE IN IDs
           $bindings[] = now(); 

           // Add the voucher IDs to the bindings for the WHERE IN clause
           foreach ($voucherIdsToUpdate as $voucherId) {
               $bindings[] = $voucherId;
           }

           $sql = "UPDATE merchant_offer_vouchers SET imported_code = (CASE " . implode(' ', $cases) . " END), updated_at = ? WHERE id IN (" . rtrim(str_repeat('?,', count($voucherIdsToUpdate)), ',') . ")";

           // Execute the single batch update query
           $updatedRows = DB::update($sql, $bindings);

           Log::debug("[Voucher Code Import] Updated {$updatedRows} merchant_offer_vouchers records.");

            // Prepare data for updating the tracking table (merchant_offer_campaign_voucher_codes)
            $trackingCasesVoucherId = [];
            $trackingBindings = [];
            $codesForInClause = [];

            foreach ($voucherIdsToUpdate as $index => $voucherId) {
                if (isset($uniqueCodes[$index])) {
                    $code = $uniqueCodes[$index];
                    $trackingCasesVoucherId[] = "WHEN code = ? THEN ?";
                    $trackingBindings[] = $code;
                    $trackingBindings[] = $voucherId; // Assign the voucher ID
                    $codesForInClause[] = $code; // Collect codes for the WHERE IN clause
                }
            }
            
            if (!empty($codesForInClause)) { // Only run if there are codes to update
                // --- Binding Order Correction ---
                // 1. Add CASE bindings (already done in the loop above)
                
                // 2. Add updated_at binding *before* WHERE clause bindings
                $trackingBindings[] = now();
                
                // 3. Add campaign ID for WHERE clause
                $trackingBindings[] = $merchantOfferCampaign->id;

                // 4. Add codes for WHERE IN clause
                foreach ($codesForInClause as $code) {
                    $trackingBindings[] = $code;
                }
                // --- End Correction ---

                $trackingSql = "UPDATE merchant_offer_campaign_voucher_codes 
                                SET is_used = true, 
                                    voucher_id = (CASE " . implode(' ', $trackingCasesVoucherId) . " END), 
                                    updated_at = ? 
                                WHERE merchant_offer_campaign_id = ? 
                                AND code IN (" . rtrim(str_repeat('?,', count($codesForInClause)), ',') . ")";

                // Execute the second batch update query for the tracking table
                $updatedTrackingRows = DB::update($trackingSql, $trackingBindings);
                Log::debug("[Voucher Code Import] Updated {$updatedTrackingRows} merchant_offer_campaign_voucher_codes tracking records.");
            } else {
                 Log::info("[Voucher Code Import] No tracking records needed updating for Campaign ID: {$merchantOfferCampaign->id}.");
                 $updatedTrackingRows = 0; // Set to 0 if no update was needed
            }
           
            DB::commit(); // Commit transaction *after* both updates succeed (or if no updates needed)
            Log::info("[Voucher Code Import] Successfully assigned {$updatedRows} codes and updated {$updatedTrackingRows} tracking records via batch update for Campaign ID: {$merchantOfferCampaign->id}.");

       } catch (Throwable $e) {
           DB::rollBack();
           Log::error("[Voucher Code Import] Error during batch code assignment for Campaign ID {$merchantOfferCampaign->id}: " . $e->getMessage(), ['exception' => $e]);
           // Re-throw the exception to be handled by the caller
           throw $e;
       }
   }
}
