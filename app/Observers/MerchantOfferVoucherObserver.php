<?php

namespace App\Observers;

use Exception;
use App\Models\MerchantOfferVoucher;
use App\Services\MixpanelService;
use Illuminate\Support\Facades\Log;

class MerchantOfferVoucherObserver
{
    /**
     * @var MixpanelService
     */
    protected $mixpanelService;

    /**
     * Constructor
     *
     * @param MixpanelService $mixpanelService
     */
    public function __construct(MixpanelService $mixpanelService)
    {
        $this->mixpanelService = $mixpanelService;
    }

    /**
     * Handle the MerchantOfferVoucher "created" event.
     *
     * @param MerchantOfferVoucher $voucher
     * @return void
     */
    public function created(MerchantOfferVoucher $voucher)
    {
        // We don't track on creation because it might not be a sale yet
    }

    /**
     * Handle the MerchantOfferVoucher "updated" event.
     *
     * @param MerchantOfferVoucher $voucher
     * @return void
     */
    public function updated(MerchantOfferVoucher $voucher)
    {
        // Track voucher sale when a voucher is claimed (owned_by_id changes from null to a value)
        if ($voucher->isDirty('owned_by_id') && $voucher->owned_by_id !== null) {
            try {
                Log::info('Tracking voucher sale for voucher ID: ' . $voucher->id);
                $this->mixpanelService->trackVoucherSale($voucher);
            } catch (Exception $e) {
                Log::error('Failed to track voucher sale in observer: ' . $e->getMessage(), [
                    'voucher_id' => $voucher->id,
                    'exception' => $e
                ]);
            }
        }
    }

    /**
     * Handle the MerchantOfferVoucher "deleted" event.
     *
     * @param MerchantOfferVoucher $voucher
     * @return void
     */
    public function deleted(MerchantOfferVoucher $voucher)
    {
        // No tracking needed for deletion
    }

    /**
     * Handle the MerchantOfferVoucher "restored" event.
     *
     * @param MerchantOfferVoucher $voucher
     * @return void
     */
    public function restored(MerchantOfferVoucher $voucher)
    {
        // No tracking needed for restoration
    }

    /**
     * Handle the MerchantOfferVoucher "force deleted" event.
     *
     * @param MerchantOfferVoucher $voucher
     * @return void
     */
    public function forceDeleted(MerchantOfferVoucher $voucher)
    {
        // No tracking needed for force deletion
    }
}
