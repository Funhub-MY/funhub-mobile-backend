<?php

namespace App\Services;

use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferCampaignVoucherCode;
use Illuminate\Support\Facades\Log;

class MerchantOfferCampaignCodeImporter
{
    /**
     * Process imported voucher codes from CSV file
     *
     * @param  MerchantOfferCampaign  $merchantOfferCampaign
     * @return void
     */
    public function processImportedCodes(MerchantOfferCampaign $merchantOfferCampaign)
    {
        try {
            // Check if there's a media item in the imported_codes collection
            $media = $merchantOfferCampaign->getMedia('imported_codes')->last();
            Log::info('Imported codes media: ' . ($media ? $media->id : 'none'));

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
}
