<?php

namespace App\Filament\Resources\PromotionCodeGroups\Pages;

use App\Filament\Resources\PromotionCodeGroups\PromotionCodeGroupResource;
use App\Jobs\GeneratePromotionCodesJob;
use App\Models\PromotionCode;
use App\Models\PromotionCodeGroup;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Reward;
use App\Models\RewardComponent;
use Filament\Notifications\Notification;

class CreatePromotionCodeGroup extends CreateRecord
{
    protected static string $resource = PromotionCodeGroupResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        Log::info('[PromotionCodeGroup] Creating promotion code group', $data);

        return DB::transaction(function () use ($data) {

            if ($data['code_type'] == PromotionCodeGroup::CODE_TYPES['random']) {
                $totalCodes = $data['total_codes'];
            }
           else {
                $totalCodes = 1;
           }

            // Initialize variables for reward data
            $rewardable_type = null;
            $rewardable_id = null;
            $quantity = null;

            // Only process reward data if not using fix amount discount
            if (!($data['discount_type'] === 'fix_amount')) {
                // rewards data
                $rewardable_type = $data['rewardable_type'] ?? null;
                $rewardable_id = $data['rewardable_id'] ?? null;
                $quantity = $data['quantity'] ?? null;

                // remove fields that don't belong in PromotionCodeGroup
                unset($data['rewardable_type']);
                unset($data['rewardable_id']);
                unset($data['quantity']);
            }

            // Create the promotion code group first
            $group = static::getModel()::create($data);

            // Prepare job data
            $jobData = $data;

            // Add back reward data if it was removed
            if (!($data['use_fix_amount_discount'] ?? false)) {
                $jobData['rewardable_type'] = $rewardable_type;
                $jobData['rewardable_id'] = $rewardable_id;
                $jobData['quantity'] = $quantity;
            }


            // Dispatch the job to generate promotion codes
            GeneratePromotionCodesJob::dispatch($group, $jobData);

            Notification::make()
                ->title('Promotion Code Group Created')
                ->body("The promotion code group has been created. {$totalCodes} codes are being generated in the background.")
                ->success()
                ->send();

            Log::info('[PromotionCodeGroup] Dispatched job to generate promotion codes', [
                'promotion_code_group_id' => $group->id,
                'total_codes' => $totalCodes
            ]);

            return $group;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
