<?php

namespace App\Observers;

use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferCampaignVoucherCode;
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
    
    /**
     * Process imported voucher codes from CSV file
     *
     * @param  \App\Models\MerchantOfferCampaign  $merchantOfferCampaign
     * @return void
     */
    private function processImportedCodes(MerchantOfferCampaign $merchantOfferCampaign)
    {
        try {
            // Check if there's a media item in the imported_codes collection
            $media = $merchantOfferCampaign->getMedia('imported_codes')->last();
            Log::info('Imported codes media: ' . $media->id);
            
            if (!$media) {
                return;
            }
            
            // Get the file path
            $filePath = $media->getPath();
            
            // Read the file content
            $fileContent = file_get_contents($filePath);
            
            if (empty($fileContent)) {
                return;
            }
            
            // Split the content by new line
            $codes = array_filter(explode(PHP_EOL, $fileContent));
            
            // Process each code
            foreach ($codes as $code) {
                $code = trim($code);
                
                // Skip empty codes
                if (empty($code)) {
                    continue;
                }
                
                // Check if the code already exists for this campaign
                $existingCode = MerchantOfferCampaignVoucherCode::where('merchant_offer_campaign_id', $merchantOfferCampaign->id)
                    ->where('code', $code)
                    ->first();
                
                // If the code doesn't exist, create it
                if (!$existingCode) {
                    MerchantOfferCampaignVoucherCode::create([
                        'merchant_offer_campaign_id' => $merchantOfferCampaign->id,
                        'code' => $code,
                        'is_used' => false
                    ]);

                    // Log the creation of the voucher code
                    Log::info('[Imported Voucher Code] Created Import voucher code: ' . $code);
                } else {
                    // Log the creation of the voucher code
                    Log::info('[Imported Voucher Code] Voucher code already exists: ' . $code);
                }
            }
        } catch (\Exception $e) {
            Log::error('[Imported Voucher Code] Error processing imported voucher codes: ' . $e->getMessage(), [
                'merchant_offer_campaign_id' => $merchantOfferCampaign->id,
                'exception' => $e
            ]);
        }
    }
}
