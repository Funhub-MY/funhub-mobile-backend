<?php

namespace App\Observers;

use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferCampaignVoucherCode;
use App\Models\MerchantOfferVoucher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MerchantOfferCampaignObserver
{
    /**
     * Handle the MerchantOfferCampaign "created" event.
     *
     * @param  \App\Models\MerchantOfferCampaign  $merchantOfferCampaign
     * @return void
     */
    public function created(MerchantOfferCampaign $merchantOfferCampaign)
    {
        $this->processImportedCodes($merchantOfferCampaign);
        $this->checkOfferVouchersHasImportedCodes($merchantOfferCampaign);
    }

    /**
     * Handle the MerchantOfferCampaign "updated" event.
     *
     * @param  \App\Models\MerchantOfferCampaign  $merchantOfferCampaign
     * @return void
     */
    public function updated(MerchantOfferCampaign $merchantOfferCampaign)
    {
        $this->processImportedCodes($merchantOfferCampaign);
        $this->checkOfferVouchersHasImportedCodes($merchantOfferCampaign);
    }

    /**
     * Handle the MerchantOfferCampaign "deleted" event.
     *
     * @param  \App\Models\MerchantOfferCampaign  $merchantOfferCampaign
     * @return void
     */
    public function deleted(MerchantOfferCampaign $merchantOfferCampaign)
    {
        //
    }

    /**
     * Handle the MerchantOfferCampaign "restored" event.
     *
     * @param  \App\Models\MerchantOfferCampaign  $merchantOfferCampaign
     * @return void
     */
    public function restored(MerchantOfferCampaign $merchantOfferCampaign)
    {
        //
    }

    /**
     * Handle the MerchantOfferCampaign "force deleted" event.
     *
     * @param  \App\Models\MerchantOfferCampaign  $merchantOfferCampaign
     * @return void
     */
    public function forceDeleted(MerchantOfferCampaign $merchantOfferCampaign)
    {
        //
    }
    
    // Use the MerchantOfferCampaignCodeImporter service for code import logic.
    private function processImportedCodes(MerchantOfferCampaign $merchantOfferCampaign)
    {
        app(\App\Services\MerchantOfferCampaignCodeImporter::class)->processImportedCodes($merchantOfferCampaign);
    }

    private function checkOfferVouchersHasImportedCodes(MerchantOfferCampaign $merchantOfferCampaign)
    {
        try {
            // Check if the campaign has any imported codes that are not used yet
            $unusedImportedCodes = MerchantOfferCampaignVoucherCode::where('merchant_offer_campaign_id', $merchantOfferCampaign->id)
                ->where('is_used', false)
                ->whereNull('voucher_id')
                ->orderBy('id', 'asc')
                ->get();
            
            if ($unusedImportedCodes->isEmpty()) {
                // No unused imported codes, nothing to do
                return;
            }
            
            // Get all merchant offers for this campaign with their vouchers in a single query
            // This uses joins to reduce DB load and CPU time as requested
            $offerVouchers = MerchantOfferVoucher::select('merchant_offer_vouchers.*')
                ->join('merchant_offers', 'merchant_offers.id', '=', 'merchant_offer_vouchers.merchant_offer_id')
                ->where('merchant_offers.merchant_offer_campaign_id', $merchantOfferCampaign->id)
                ->whereNull('merchant_offer_vouchers.imported_code') // Only get vouchers without imported codes
                ->orderBy('merchant_offer_vouchers.id', 'asc')
                ->get();
            
            if ($offerVouchers->isEmpty()) {
                // No vouchers to assign codes to
                return;
            }
            
            // Assign imported codes to vouchers
            $codeIndex = 0;
            $totalCodes = $unusedImportedCodes->count();
            
            foreach ($offerVouchers as $voucher) {
                if ($codeIndex >= $totalCodes) {
                    // No more codes to assign
                    break;
                }
                
                $importedCode = $unusedImportedCodes[$codeIndex];
                
                // Update the voucher with the imported code
                $voucher->imported_code = $importedCode->code;
                $voucher->save();
                
                // Mark the imported code as used and associate it with this voucher
                $importedCode->is_used = true;
                $importedCode->voucher_id = $voucher->id;
                $importedCode->save();
                
                $codeIndex++;
            }
            
            Log::info('[Imported Voucher Code] Assigned ' . $codeIndex . ' imported codes to vouchers for campaign: ' . $merchantOfferCampaign->id);
        } catch (\Exception $e) {
            Log::error('[Imported Voucher Code] Error assigning imported codes to vouchers: ' . $e->getMessage(), [
                'merchant_offer_campaign_id' => $merchantOfferCampaign->id,
                'exception' => $e
            ]);
        }
    }
}
