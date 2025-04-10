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
            
            // Handle file reading based on storage driver
            try {
                // Try to get a temporary URL (works for S3 and other cloud storage)
                if (method_exists($media, 'getTemporaryUrl')) {
                    $fileUrl = $media->getTemporaryUrl(now()->addMinutes(5));
                    Log::info('[Imported Voucher Code] File URL: ' . $fileUrl);
                    $fileContent = file_get_contents($fileUrl);
                } else {
                    // Fallback to local path for local storage
                    $filePath = $media->getPath();
                    Log::info('[Imported Voucher Code] File path: ' . $filePath);
                    $fileContent = file_get_contents($filePath);
                }
            } catch (\Exception $e) {
                Log::error('[Imported Voucher Code] Error reading file: ' . $e->getMessage());
                return;
            }
            
            if (empty($fileContent)) {
                Log::info('[Imported Voucher Code] File content is empty');
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
