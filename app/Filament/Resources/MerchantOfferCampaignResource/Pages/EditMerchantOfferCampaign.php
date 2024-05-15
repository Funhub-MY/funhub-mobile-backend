<?php

namespace App\Filament\Resources\MerchantOfferCampaignResource\Pages;

use App\Filament\Resources\MerchantOfferCampaignResource;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferVoucher;
use Carbon\Carbon;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditMerchantOfferCampaign extends EditRecord
{
    protected static string $resource = MerchantOfferCampaignResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }


    protected function afterSave(): void
    {
        $record = $this->record; // campaign

        // update relevant MerchantOffer records
        $offers = MerchantOffer::where('merchant_offer_campaign_id', $record->id)->get();

        foreach ($offers as $offer)
        {
            $offer->update([
                'name' => $record->name,
                'description' => $record->description,
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
                'expiry_days' => $record->expiry_days,
                'status' => $record->status,
                'user_id' => $record->user_id, // requires for store selection as well
            ]);

            // replace images
            $offer->clearMediaCollection(MerchantOffer::MEDIA_COLLECTION_NAME);
            $offer->clearMediaCollection(MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);

            // ensure new images are synced
            $model = MerchantOfferCampaign::find($record->id);
            $mediaItems = $model->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_NAME);
            foreach ($mediaItems as $mediaItem) {
                $mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_NAME);
            }

            $mediaItems = $model->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_HORIZONTAL_BANNER);
            foreach ($mediaItems as $mediaItem) {
                $mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);
            }

            // clear all categories
            $offer->allOfferCategories()->detach();

            // sync latest merchant offer categories
            $offer->allOfferCategories()->sync($record->allOfferCategories->pluck('id'));

            // clear all stores
            $offer->stores()->detach();

            // sync latest campaign stores to offer stores
            $offer->stores()->sync($record->stores->pluck('id'));
        }

        // updating schedules and quantity
        foreach ($record->schedules as $schedule) {
            // if schedule available_at and available_until is past, cannot update
            // if not past can update
            if (Carbon::now()->gte(Carbon::parse($schedule->available_until)) || Carbon::now()->gte(Carbon::parse($schedule->available_at))) {
                Log::info('Cannot update schedule as available_at/until is past', [
                    'schedule_id' => $schedule->id,
                    'offer_id' => $offer->id,
                    'available_until' => $schedule->available_until,
                ]);
                continue;
            }

            // update offer available_at, available_until
            $offer = MerchantOffer::where('schedule_id', $schedule->id)->first();

            if (!$offer) {
                Log::info('Offer not found for schedule', [
                    'schedule_id' => $schedule->id,
                ]);
                continue;
            }

            $offer->update([
                'available_at' => $schedule->available_at,
                'available_until' => $schedule->available_until,
            ]);

            // match quantity difference and update
            $existingVouchers = $offer->vouchers()->count();

            // if schedule -> quantity > existing vouchers, create new vouchers
            // if less than, destroy unclaimed vouchers
            if ($schedule->quantity > $existingVouchers) {
                $diff = $schedule->quantity - $existingVouchers;
                MerchantOfferVoucher::create([
                    'merchant_offer_id' => $offer->id,
                    'code' => MerchantOfferVoucher::generateCode(),
                ]);

                Log::info('Created new vouchers as adjusted in merchant campaign schedule', [
                    'offer_id' => $offer->id,
                    'schedule_id' => $schedule->id,
                    'quantity' => $diff,
                ]);
            } else if ($schedule->quantity < $existingVouchers) {
                $diff = $existingVouchers - $schedule->quantity;
                $offer->vouchers()->whereNull('owned_by_id')->limit($diff)->get();

                // log deleted vouchers
                Log::info('Deleted unclaimed vouchers as adjusted in merchant campaign schedule', [
                    'offer_id' => $offer->id,
                    'schedule_id' => $schedule->id,
                    'quantity' => $diff,
                ]);

                // delete vouchers (unclaimed only)
                $offer->vouchers()->whereNull('owned_by_id')->limit($diff)->delete();
            }

            // final value add it back as schdule's quantity as some claimed vouchers no longer can be removed
            $schedule->update([
                'quantity' => $offer->vouchers()->count(),
            ]);

            // updatye quantity in offer too (but only unclaimed vouchers quantity!)
            $offer->update([
                'quantity' => $offer->unclaimedVouchers()->count(),
            ]);
        }
    }
}
