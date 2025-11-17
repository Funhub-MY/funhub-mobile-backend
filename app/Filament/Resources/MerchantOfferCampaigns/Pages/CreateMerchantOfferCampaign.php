<?php

namespace App\Filament\Resources\MerchantOfferCampaigns\Pages;

use App\Filament\Resources\MerchantOfferCampaigns\MerchantOfferCampaignResource;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferVoucher;
use App\Services\MerchantCampaignProcessor;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Services\MerchantOfferCampaignCodeImporter; // <-- added import
use Illuminate\Database\Eloquent\Model;

class CreateMerchantOfferCampaign extends CreateRecord
{
    protected static string $resource = MerchantOfferCampaignResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // unset select_all_stores
        unset($data['select_all_stores']);
        
        $startDate = Carbon::parse($data['start_date']);
        $endDate = isset($data['end_date']) ? Carbon::parse($data['end_date']) : null;
        $vouchersCount = (int) $data['vouchers_count'];
        $daysPerSchedule = (int) $data['days_per_schedule'];
        $intervalDays = isset($data['interval_days']) ? (int) $data['interval_days'] : 0;
        $availableQuantityPerSchedule = (int) $data['available_quantity'];

        $schedules = [];
        $remainingVouchers = $vouchersCount;
        $scheduleCount = ceil($vouchersCount / $availableQuantityPerSchedule);

        for ($i = 0; $i < $scheduleCount; $i++) {
            $scheduleStartDate = $startDate->copy()->addDays(($daysPerSchedule + $intervalDays) * $i);
            $scheduleEndDate = $scheduleStartDate->copy()->addDays($daysPerSchedule - 1);

            if ($endDate && $scheduleEndDate->gt($endDate)) {
                $scheduleEndDate = $endDate;
            }

            $scheduleVouchers = min($availableQuantityPerSchedule, $remainingVouchers);

            $schedules[] = [
                'status' => 0,
                'publish_at' => $scheduleStartDate->format('Y-m-d'),
                'available_at' => $scheduleStartDate->format('Y-m-d H:i:s'),
                'available_until' => $scheduleEndDate->format('Y-m-d 23:59:59'),
                'quantity' => $scheduleVouchers,
                'user_id' => auth()->id(),
            ];

            Log::info('[CampaignScheduler] Schedule created', [
                'schedule' => $schedules[$i],
            ]);

            $remainingVouchers -= $scheduleVouchers;

            if ($remainingVouchers <= 0 || ($endDate && $scheduleEndDate->gte($endDate))) {
                break;
            }
        }

        // Set merchant_id if user has a merchant
        if (!isset($data['merchant_id']) && isset($data['user_id'])) {
            $merchant = \App\Models\Merchant::where('user_id', $data['user_id'])->first();
            if ($merchant) {
                $data['merchant_id'] = $merchant->id;
            }
        }
        
        $model = $this->getModel()::create($data);

        $schedulesWithCampaignId = array_map(function ($schedule) use ($model) {
            $schedule['merchant_offer_campaign_id'] = $model->id;
            $schedule['created_at'] = now();
            $schedule['updated_at'] = now();
            return $schedule;
        }, $schedules);

        $model->schedules()->insert($schedulesWithCampaignId);

        // // Directly process imported codes (create voucher codes from imported file, if any) before processing
        // app(MerchantOfferCampaignCodeImporter::class)->processImportedCodes($model);

        return $model;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        try {
            // Process campaign synchronously with transaction protection
            $processor = app(MerchantCampaignProcessor::class);
            $results = $processor->processCampaign($record);
            
            if ($results['success']) {
                Notification::make()
                    ->success()
                    ->title('Campaign Processed Successfully')
                    ->body(sprintf(
                        'Created %d offers and %d vouchers in %.1f seconds.',
                        $results['offers_created'],
                        $results['vouchers_created'],
                        $results['duration_seconds'] ?? 0
                    ))
                    ->send();
            } else {
                $errorCount = count($results['errors']);
                Notification::make()
                    ->warning()
                    ->title('Campaign Processed with Errors')
                    ->body(sprintf(
                        'Created %d offers and %d vouchers, but %d error(s) occurred. Check logs for details.',
                        $results['offers_created'],
                        $results['vouchers_created'],
                        $errorCount
                    ))
                    ->send();
                
                Log::error('[CreateMerchantOfferCampaign] Processing completed with errors', $results);
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Campaign Processing Failed')
                ->body('An error occurred while processing the campaign. Please check the logs and try again.')
                ->send();
            
            Log::error('[CreateMerchantOfferCampaign] Processing failed', [
                'campaign_id' => $record->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    public function addAvailableVouchers(): void
    {
        $userId = $this->data['user_id'] ?? null;
        
        if (!$userId) {
            Notification::make()
                ->warning()
                ->title('Please select a merchant first')
                ->send();
            return;
        }
        
        // Use merchant_id if available, fallback to user_id
        $merchant = auth()->user()->merchant;
        $available = MerchantOffer::whereHas('campaign', function ($query) use ($userId, $merchant) {
                if ($merchant && $merchant->id) {
                    $query->where(function($q) use ($merchant, $userId) {
                        $q->where('merchant_id', $merchant->id)
                          ->orWhere('user_id', $userId);
                    });
                } else {
                    $query->where('user_id', $userId);
                }
            })
            ->withCount([
                'vouchers as available_count' => function ($query) {
                    $query->whereNull('owned_by_id')
                        ->where('voided', false);
                }
            ])
            ->get()
            ->sum('available_count');
        
        $currentValue = (int)($this->data['vouchers_count'] ?? 0);
        $newValue = $currentValue + $available;
        $this->data['vouchers_count'] = $newValue;
        
        Notification::make()
            ->success()
            ->title('Vouchers Added')
            ->body("Added {$available} available vouchers. New total: {$newValue}")
            ->send();
    }
}
