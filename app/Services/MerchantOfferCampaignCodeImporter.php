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
     * Imports voucher codes and assigns them to existing MerchantOfferVoucher records
     * associated with the campaign that do not yet have a code.
     *
     * @param MerchantOfferCampaign $merchantOfferCampaign The campaign to import codes for.
     * @param array $codes An array of unique voucher codes to import.
     * @throws \Exception If the number of codes does not match the number of empty vouchers.
     */
    public function importCodes(MerchantOfferCampaign $merchantOfferCampaign, array $codes): void
    {
        // Ensure codes are unique within the input array
        $uniqueCodes = array_unique(array_filter(array_map('trim', $codes)));
        $importCount = count($uniqueCodes);

        if ($importCount === 0) {
            Log::warning('[Voucher Code Import] No unique codes provided for import.', ['campaign_id' => $merchantOfferCampaign->id]);
            // Depending on requirements, you might throw an exception or just return
            throw new \InvalidArgumentException('No unique codes provided for import.');
        }

        DB::beginTransaction();

        try {
            // Find existing MerchantOfferVoucher records for this campaign's offers that need a code
            $vouchersToUpdate = MerchantOfferVoucher::query()
                ->whereHas('merchant_offer', function ($query) use ($merchantOfferCampaign) { // Use snake_case to match model method
                    $query->where('merchant_offer_campaign_id', $merchantOfferCampaign->id);
                })
                ->whereNull('imported_code') // Find vouchers without a imported code
                ->whereNull('owned_by_id') // not purcahsed before this
                ->lockForUpdate() // Lock rows to prevent race conditions during update
                ->get();

            $emptyVoucherCount = $vouchersToUpdate->count();

            // Validate counts - IMPORTANT: This assumes one imported code per empty voucher slot
            if ($importCount !== $emptyVoucherCount) {
                 throw new \Exception("Quantity Mismatch: The number of imported codes ({$importCount}) does not match the number of available empty voucher slots ({$emptyVoucherCount}) for this campaign.");
            }

            // Assign codes to vouchers and update tracking records
            $codeIndex = 0;
            foreach ($vouchersToUpdate as $voucher) {
                $currentCode = $uniqueCodes[$codeIndex];

                // 1. Assign code to the actual MerchantOfferVoucher
                $voucher->imported_code = $currentCode; // Assuming imported_code based on user's previous edit
                $voucher->save();

                // 2. Find and update the corresponding tracking record
                $trackingCode = MerchantOfferCampaignVoucherCode::where('merchant_offer_campaign_id', $merchantOfferCampaign->id)
                    ->where('code', $currentCode)
                    ->first();

                if ($trackingCode) {
                    $trackingCode->voucher_id = $voucher->id; // Link to the actual voucher
                    $trackingCode->is_used = true; // Mark as used/assigned
                    $trackingCode->save();

                    Log::info('[Voucher Code Import] Assigned code: ' . $currentCode . ' to Voucher ID: ' . $voucher->id . ' and updated Tracking Code ID: ' . $trackingCode->id . ' for Campaign ID: ' . $merchantOfferCampaign->id);
                } else {
                    // This case might indicate an inconsistency if tracking codes should always exist before assignment
                    Log::warning('[Voucher Code Import] Could not find tracking record for code: ' . $currentCode . ' for Campaign ID: ' . $merchantOfferCampaign->id . '. Voucher ID ' . $voucher->id . ' was updated with the code, but tracking record was not.', [
                        'campaign_id' => $merchantOfferCampaign->id,
                        'code' => $currentCode,
                        'assigned_voucher_id' => $voucher->id,
                    ]);
                    // Decide if this should throw an error and roll back the transaction
                    // For now, we just log a warning.
                    // throw new \Exception('Failed to find tracking record for code: ' . $currentCode);
                }

                $codeIndex++;
            }

            DB::commit();

            Log::info('[Voucher Code Import] Successfully assigned ' . $importCount . ' codes to vouchers for Campaign ID: ' . $merchantOfferCampaign->id);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('[Voucher Code Import] Error processing imported voucher codes: ' . $e->getMessage(), [
                'campaign_id' => $merchantOfferCampaign->id,
                'exception' => $e
            ]);
            // Re-throw the exception so the Filament action can catch it
            throw $e;
        }
    }
}
