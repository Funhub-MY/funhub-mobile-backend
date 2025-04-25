<?php

namespace App\Filament\Resources\MerchantOfferCampaignResource\Pages;

use App\Filament\Resources\MerchantOfferCampaignResource;
use App\Jobs\CreateMerchantOfferJob;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferVoucher;
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

        $model = $this->getModel()::create($data);

        $schedulesWithCampaignId = array_map(function ($schedule) use ($model) {
            $schedule['merchant_offer_campaign_id'] = $model->id;
            $schedule['created_at'] = now();
            $schedule['updated_at'] = now();
            return $schedule;
        }, $schedules);

        $model->schedules()->insert($schedulesWithCampaignId);

        // // Directly process imported codes (create voucher codes from imported file, if any) before dispatching jobs
        // app(MerchantOfferCampaignCodeImporter::class)->processImportedCodes($model);

        // notification
        Notification::make()
            ->success()
            ->title('Merchant offers queued for generation')
            ->body('Each schedule merchant offers will be created one by one in the background. Please check back in 10-15mins time.')
            ->send();

        return $model;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        // use queued job to prevent long wait time when create merhchant offer camapaign
        foreach ($record->schedules as $schedule) {
            CreateMerchantOfferJob::dispatch($record->id, $schedule->id);
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
        
        $available = \App\Models\MerchantOffer::whereHas('campaign', function ($query) use ($userId) {
                $query->where('user_id', $userId);
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
