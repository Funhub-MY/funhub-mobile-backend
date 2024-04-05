<?php

namespace App\Filament\Resources\MerchantOfferCampaignResource\Pages;

use App\Filament\Resources\MerchantOfferCampaignResource;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferVoucher;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CreateMerchantOfferCampaign extends CreateRecord
{
    protected static string $resource = MerchantOfferCampaignResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;
        // create merchant offers based on schedules
        foreach($this->record->schedules as $index => $schedule) {
            // create a new merchant offer per schedule (diffrentiated by aviailable_at and available_until)
            $offer = MerchantOffer::create([
                'user_id' => $record->user_id,
                'store_id' => $record->store_id ?? null,
                'merchant_offer_campaign_id' => $record->id,
                'schedule_id' => $schedule->id, // record schedule id so when update can update correct offer available_at and until
                'name' => $record->name,
                'description' => $record->description,
                'sku' => $record->sku . '-' . $index+1,
                'fine_print' => $record->fine_print,
                'redemption_policy' => $record->redemption_policy,
                'cancellation_policy' => $record->cancellation_policy,
                'publish_at' => $record->publish_at,
                'purchase_method' => $record->purchase_method,
                'unit_price' => $record->unit_price,
                'discounted_point_fiat_price' => $record->discounted_point_fiat_price,
                'point_fiat_price' => $record->point_fiat_price,
                'discounted_fiat_price' => $record->discounted_fiat_price,
                'fiat_price' => $record->fiat_price,
                'expiry_days' => ($schedule->expiry_days ?? $record->expiry_days),
                'available_at' => $schedule->available_at,
                'available_until' => $schedule->available_until,
                'quantity' => $schedule->quantity,
                'status' => $record->status,
            ]);

            // Copy media from MerchantOfferCampaign to MerchantOffer
            $model = MerchantOfferCampaign::find($record->id);
            $mediaItems = $model->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_NAME);
            foreach ($mediaItems as $mediaItem) {
                $mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_NAME);
            }

            $mediaItems = $model->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_HORIZONTAL_BANNER);
            foreach ($mediaItems as $mediaItem) {
                $mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);
            }

            // sync merchant offer campaign categories to similar merchant offer
            $offer->allOfferCategories()->sync($record->allOfferCategories->pluck('id'));

            // create vouchers per offer
            $quantity = $schedule->quantity;
            for($i = 0; $i < $quantity; $i++) {
                MerchantOfferVoucher::create([
                    'merchant_offer_id' => $offer->id,
                    'code' => MerchantOfferVoucher::generateCode(),
                ]);
            }
        }
    }
}
