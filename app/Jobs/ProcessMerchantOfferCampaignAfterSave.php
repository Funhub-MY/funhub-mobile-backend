<?php

namespace App\Jobs;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferCampaignSchedule;
use App\Models\MerchantOfferVoucher;
use App\Jobs\SyncMerchantOfferStores;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMerchantOfferCampaignAfterSave implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	protected $record;

	/**
	 * Create a new job instance.
	 *
	 * @param MerchantOfferCampaign $record
	 * @return void
	 */
	public function __construct(MerchantOfferCampaign $record)
	{
		$this->record = $record;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle()
	{
		Log::info("[Process Merchant offer campaign] after save start Dispatch");

		// Eager load all necessary relationships
		$record = $this->record->load([
			'stores',
			'allOfferCategories',
			'schedules',
			'merchantOffers',
			'media',
		]);
		$campaignStoreIds = $record->stores->pluck('id')->toArray();
		$categoryIds = $record->allOfferCategories->pluck('id')->toArray();
		$mediaGallery = $record->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_NAME);
		$mediaBanner = $record->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_HORIZONTAL_BANNER);

		// Chunk offers for memory efficiency
		MerchantOffer::where('merchant_offer_campaign_id', $record->id)
			->with(['allOfferCategories', 'media'])
			->chunk(200, function ($offers) use ($record, $campaignStoreIds, $categoryIds, $mediaGallery, $mediaBanner) {
				foreach ($offers as $offer) {
					$isArchived = $offer->status === MerchantOffer::STATUS_ARCHIVED;

					$offer->update([
						'name' => $record->name,
						'highlight_messages' => $record->highlight_messages,
						'description' => $record->description,
						'fine_print' => $record->fine_print,
						'available_for_web' => $record->available_for_web,
						'redemption_policy' => $record->redemption_policy,
						'cancellation_policy' => $record->cancellation_policy,
						// 'publish_at' => $record->publish_at,
						'purchase_method' => $record->purchase_method,
						'unit_price' => $record->unit_price,
						'discounted_point_fiat_price' => $record->discounted_point_fiat_price,
						'point_fiat_price' => $record->point_fiat_price,
						'discounted_fiat_price' => $record->discounted_fiat_price,
						'fiat_price' => $record->fiat_price,
						'expiry_days' => $record->expiry_days,
						'user_id' => $record->user_id, // requires for store selection as well
					]);

					// If the offer was archived, restore its status to archived
					if ($isArchived) {
						$offer->update(['status' => MerchantOffer::STATUS_ARCHIVED]);
					}

					// replace images
					$offer->clearMediaCollection(MerchantOffer::MEDIA_COLLECTION_NAME);
					$offer->clearMediaCollection(MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);
					foreach ($mediaGallery as $mediaItem) {
						$mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_NAME);
					}
					foreach ($mediaBanner as $mediaItem) {
						$mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);
					}

					// Sync categories in bulk
					$offer->allOfferCategories()->sync($categoryIds);

					// Dispatch store sync job (optionally batch or delay if needed)
					SyncMerchantOfferStores::dispatch($offer->id, $campaignStoreIds);

					// Sync Algolia
					try {
						$offer->searchable();
					} catch (\Exception $e) {
						Log::error('[Process Merchant offer campaign] Error syncing algolia', ['error' => $e->getMessage()]);
					}
				}
			});

		// archieve any offer that has been removed as of latest $record schedules
		$offers = MerchantOffer::where('merchant_offer_campaign_id', $record->id)->get();
		if ($offers->count() > $record->schedules->count()) {
			$schedulesIds = $record->schedules->pluck('id');
			$offers->whereNotIn('schedule_id', $schedulesIds)->each(function($offer) {
				// double check if offer already has voucher sold or not
				if ($offer->vouchers()->whereNotNull('owned_by_id')->count() > 0) {
					Log::info('[Process Merchant offer campaign] Cannot archieve offer as it has been sold', [
						'offer_id' => $offer->id,
						'schedule_id' => $offer->schedule_id,
					]);
					return;
				}

				$offer->delete(); // soft delete
				Log::info('[Process Merchant offer campaign] Archieved offer as schedule is removed, offer deleted', [
					'offer_id' => $offer->id,
					'schedule_id' => $offer->schedule_id,
				]);
			});
		}

		// updating schedules and quantity
		foreach ($record->schedules as $index => $schedule) {
			// Store the original status to preserve it if it was archived
			$originalStatus = $schedule->status;
			$wasArchived = $originalStatus === MerchantOfferCampaignSchedule::STATUS_ARCHIVED;

			// if schedule available_at and available_until is past, cannot update
			// if not past can update
			if (Carbon::now()->gte(Carbon::parse($schedule->available_until)) || Carbon::now()->gte(Carbon::parse($schedule->available_at))) {
				Log::info('[Process Merchant offer campaign] Cannot update schedule as available_at/until is past', [
					'schedule_id' => $schedule->id,
					'offer_id' => isset($offer) ? $offer->id : null,
					'available_until' => $schedule->available_until,
				]);

				// If the schedule was archived, ensure it stays archived
				if ($wasArchived) {
					$schedule->update(['status' => MerchantOfferCampaignSchedule::STATUS_ARCHIVED]);
				}
				continue;
			}

			// update offer available_at, available_until
			$offer = MerchantOffer::where('schedule_id', $schedule->id)->first();

			if (!$offer) {
				// Offer not found, user added new schedule and offer is not created yet
				Log::info('[Process Merchant offer campaign] Offer not found for schedule', [
					'schedule_id' => $schedule->id,
				]);

				// create offer directly
				$offer = MerchantOffer::create([
					'user_id' => $record->user_id,
					'store_id' => $record->store_id ?? null,
					'merchant_offer_campaign_id' => $record->id,
					'schedule_id' => $schedule->id, // record schedule id so when update can update correct offer available_at and until
					'name' => $record->name,
					'highlight_messages' => $record->highlight_messages,
					'description' => $record->description,
					'available_for_web' => $record->available_for_web,
					'sku' => $record->sku . '-' . $index+1,
					'fine_print' => $record->fine_print,
					'redemption_policy' => $record->redemption_policy,
					'cancellation_policy' => $record->cancellation_policy,
					'publish_at' => $schedule->publish_at,
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
					'status' => $schedule->status,
				]);
				foreach ($mediaGallery as $mediaItem) {
					$mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_NAME);
				}
				foreach ($mediaBanner as $mediaItem) {
					$mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);
				}
				$offer->allOfferCategories()->sync($categoryIds);
				SyncMerchantOfferStores::dispatch($offer->id, $campaignStoreIds);
				// Bulk insert vouchers
				$quantity = $schedule->quantity;
				$voucherData = [];
				for ($i = 0; $i < $quantity; $i++) {
					$voucherData[] = [
						'merchant_offer_id' => $offer->id,
						'code' => MerchantOfferVoucher::generateCode(),
						'created_at' => now(),
						'updated_at' => now(),
					];
				}
				if (!empty($voucherData)) {
					MerchantOfferVoucher::insert($voucherData);
				}
			} else {
				// offer exists, just update the new schedule

				// auto publish if available_at is less than or equal to current time
				$status = Carbon::parse($schedule->available_at)->lte(Carbon::now())
					? MerchantOffer::STATUS_PUBLISHED
					: MerchantOffer::STATUS_DRAFT;

				// Check if the offer is already archived
				$isOfferArchived = $offer->status === MerchantOffer::STATUS_ARCHIVED;

				$offer->update([
					'available_at' => $schedule->available_at,
					'available_until' => $schedule->available_until,
					'status' => $isOfferArchived ? MerchantOffer::STATUS_ARCHIVED : $status,
					'publish_at' => $schedule->publish_at,
				]);

				// If the schedule was archived, ensure it stays archived
				if ($wasArchived) {
					$schedule->update(['status' => MerchantOfferCampaignSchedule::STATUS_ARCHIVED]);
				}

				// match quantity difference and update
				$existingVouchers = $offer->vouchers()->count();

				// if schedule -> quantity > existing vouchers, create new vouchers
				// if less than, destroy unclaimed vouchers
				if ($schedule->quantity > $existingVouchers) {
					$diff = $schedule->quantity - $existingVouchers;
					$voucherData = [];
					for ($i = 0; $i < $diff; $i++) {
						$voucherData[] = [
							'merchant_offer_id' => $offer->id,
							'code' => MerchantOfferVoucher::generateCode(),
							'created_at' => now(),
							'updated_at' => now(),
						];
					}
					if (!empty($voucherData)) {
						MerchantOfferVoucher::insert($voucherData);
					}
					Log::info('[Process Merchant offer campaign] Created new vouchers as adjusted in merchant campaign schedule', [
						'offer_id' => $offer->id,
						'schedule_id' => $schedule->id,
						'quantity' => $diff,
					]);
				} else if ($schedule->quantity < $existingVouchers) {
					$diff = $existingVouchers - $schedule->quantity;
					$unclaimed = $offer->vouchers()->whereNull('owned_by_id')->limit($diff)->get();
					$idsToDelete = $unclaimed->pluck('id');
					if ($idsToDelete->count() > 0) {
						MerchantOfferVoucher::whereIn('id', $idsToDelete)->delete();
					}
					Log::info('[Process Merchant offer campaign] Deleted unclaimed vouchers as adjusted in merchant campaign schedule', [
						'offer_id' => $offer->id,
						'schedule_id' => $schedule->id,
						'quantity' => $diff,
					]);
				}
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
		Log::info("[Process Merchant offer campaign] After save Dispatched Done");
	}
}