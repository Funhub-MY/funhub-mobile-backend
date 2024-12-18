<?php

namespace App\Filament\Resources\PromotionCodeGroupResource\Pages;

use App\Filament\Resources\PromotionCodeGroupResource;
use App\Models\PromotionCode;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Reward;
use App\Models\RewardComponent;

class CreatePromotionCodeGroup extends CreateRecord
{
    protected static string $resource = PromotionCodeGroupResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        Log::info($data);
        return DB::transaction(function () use ($data) {
            $totalCodes = $data['total_codes'];

            // rewards data
            $rewardable_type = $data['rewardable_type'];
            $rewardable_id = $data['rewardable_id'];
            $quantity = $data['quantity'];

            // remove fields that don't belong in PromotionCodeGroup
            unset($data['rewardable_type']);
            unset($data['rewardable_id']);
            unset($data['quantity']);

            // create the promotion code group
            $group = static::getModel()::create($data);

            for ($i = 0; $i < $totalCodes; $i++) {
                $code = PromotionCode::create([
                    'code' => PromotionCode::generateUniqueCode(),
                    'promotion_code_group_id' => $group->id,
                    'status' => $group->status,
                ]);

                if ($rewardable_type === Reward::class) {
                    $code->reward()->attach($rewardable_id, ['quantity' => $quantity]);
                } else if ($rewardable_type === RewardComponent::class) {
                    $code->rewardComponent()->attach($rewardable_id, ['quantity' => $quantity]);
                }

                Log::info('[PromotionCodeGroup] Created Promotion Code', [
                    'promotion_code_group_id' => $group->id,
                    'code' => $code->code,
                    'rewardable_type' => $rewardable_type,
                    'rewardable_id' => $rewardable_id,
                    'quantity' => $quantity,
                ]);
            }

            return $group;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
