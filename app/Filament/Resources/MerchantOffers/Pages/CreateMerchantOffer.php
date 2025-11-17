<?php

namespace App\Filament\Resources\MerchantOffers\Pages;

use App\Filament\Resources\MerchantOffers\MerchantOfferResource;
use App\Models\MerchantOfferVoucher;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMerchantOffer extends CreateRecord
{
    protected static string $resource = MerchantOfferResource::class;

    public $current_locale;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['currency'] = 'MYR';
        return $data;
    }
    protected function afterCreate(): void
    {
        // depending on merchant offer quantity specificed, create vouchers
        $record = $this->record;

        $quantity = $record->quantity;
        
        // Validate against agreement_quantity if offer has a campaign
        if ($record->merchant_offer_campaign_id && $record->campaign) {
            $campaign = $record->campaign;
            if ($campaign->agreement_quantity > 0) {
                $currentVoucherCount = \App\Models\MerchantOfferVoucher::whereHas('merchant_offer', function ($query) use ($campaign) {
                    $query->where('merchant_offer_campaign_id', $campaign->id);
                })->count();
                
                $maxAllowed = $campaign->agreement_quantity - $currentVoucherCount;
                $quantity = min($quantity, $maxAllowed);
                
                if ($quantity <= 0) {
                    \Illuminate\Support\Facades\Log::warning('[CreateMerchantOffer] Cannot create vouchers - agreement quantity reached', [
                        'campaign_id' => $campaign->id,
                        'agreement_quantity' => $campaign->agreement_quantity,
                        'current_vouchers' => $currentVoucherCount,
                        'offer_id' => $record->id,
                    ]);
                    return;
                }
            }
        }
        
        // Process vouchers in chunks for better performance
        $chunkSize = 500;
        $now = now();
        
        for ($chunk = 0; $chunk < $quantity; $chunk += $chunkSize) {
            $chunkQuantity = min($chunkSize, $quantity - $chunk);
            $voucherData = [];
            
            for ($i = 0; $i < $chunkQuantity; $i++) {
                $voucherData[] = [
                    'merchant_offer_id' => $record->id,
                    'code' => MerchantOfferVoucher::generateCode(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            
            if (!empty($voucherData)) {
                MerchantOfferVoucher::insert($voucherData);
            }
        }
    }
}
