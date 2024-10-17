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
use Illuminate\Database\Eloquent\Model;

class CreateMerchantOfferCampaign extends CreateRecord
{
    protected static string $resource = MerchantOfferCampaignResource::class;

    protected function handleRecordCreation(array $data): Model
    {
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
}
