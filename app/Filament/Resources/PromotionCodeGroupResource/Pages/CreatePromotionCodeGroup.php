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
            $batchSize = 1000; // Insert 1000 records at a time

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

            // Generate all codes first
            $codes = [];
            $rewardPivotData = [];
            $now = now();

            for ($i = 0; $i < $totalCodes; $i++) {
                $code = PromotionCode::generateUniqueCode();
                $codes[] = [
                    'code' => $code,
                    'promotion_code_group_id' => $group->id,
                    'status' => $group->status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // insert codes in batches
            foreach (array_chunk($codes, $batchSize) as $batch) {
                $insertedCodes = DB::table('promotion_codes')->insert($batch);
            }

            // get all inserted promotion codes
            $promotionCodes = PromotionCode::where('promotion_code_group_id', $group->id)->get();

            // prepare reward pivot data
            foreach ($promotionCodes as $code) {
                if ($rewardable_type === Reward::class) {
                    $rewardPivotData[] = [
                        'promotion_code_id' => $code->id,
                        'rewardable_id' => $rewardable_id,
                        'rewardable_type' => $rewardable_type,
                        'quantity' => $quantity,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                } else if ($rewardable_type === RewardComponent::class) {
                    $rewardPivotData[] = [
                        'promotion_code_id' => $code->id,
                        'rewardable_id' => $rewardable_id,
                        'rewardable_type' => $rewardable_type,
                        'quantity' => $quantity,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            // insert reward pivot data in batches
            foreach (array_chunk($rewardPivotData, $batchSize) as $batch) {
                DB::table('promotion_code_rewardable')->insert($batch);
            }

            Log::info('[PromotionCodeGroup] Created Promotion Codes', [
                'promotion_code_group_id' => $group->id,
                'total_codes' => $totalCodes,
                'rewardable_type' => $rewardable_type,
                'rewardable_id' => $rewardable_id,
                'quantity' => $quantity,
            ]);

            return $group;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
