<?php

namespace App\Jobs;

use App\Models\PromotionCode;
use App\Models\PromotionCodeGroup;
use App\Models\Reward;
use App\Models\RewardComponent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeneratePromotionCodesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The promotion code group data.
     *
     * @var array
     */
    protected $groupData;

    /**
     * The promotion code group model.
     *
     * @var PromotionCodeGroup
     */
    protected $group;

    /**
     * Create a new job instance.
     *
     * @param PromotionCodeGroup $group
     * @param array $groupData
     * @return void
     */
    public function __construct(PromotionCodeGroup $group, array $groupData)
    {
        $this->group = $group;
        $this->groupData = $groupData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('[GeneratePromotionCodesJob] Starting job', [
            'promotion_code_group_id' => $this->group->id,
            'total_codes' => $this->groupData['total_codes'] ?? 0,
        ]);

        Log::info('[GeneratePromotionCodesJob] Group data', $this->groupData);

        DB::transaction(function () {
            $totalCodes = $this->groupData['total_codes'] ?? 0;
            $batchSize = 1000; // Insert 1000 records at a time

            // Initialize variables
            $rewardable_type = null;
            $rewardable_id = null;
            $quantity = null;

            // Only process reward data if not using fix amount discount
            if (!($this->groupData['use_fix_amount_discount'] ?? false)) {
                // rewards data
                $rewardable_type = $this->groupData['rewardable_type'] ?? null;
                $rewardable_id = $this->groupData['rewardable_id'] ?? null;
                $quantity = $this->groupData['quantity'] ?? null;
            }

            $now = now();

            // Generate all random codes first
            if ($this->groupData['code_type'] === 'random') {

                $codes = [];

                for ($i = 0; $i < $totalCodes; $i++) {
                    // Generate the base code
                    $code = PromotionCode::generateUniqueCode();

                    $codes[] = [
                        'code' => $code,
                        'promotion_code_group_id' => $this->group->id,
                        'status' => $this->group->status,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    // Log progress for large batches
                    if ($totalCodes >= 10000 && $i % 10000 === 0 && $i > 0) {
                        Log::info("[GeneratePromotionCodesJob] Generated {$i} codes so far");
                    }
                }

                // insert codes in batches
                $insertedCount = 0;
                foreach (array_chunk($codes, $batchSize) as $batch) {
                    DB::table('promotion_codes')->insert($batch);
                    $insertedCount += count($batch);

                    // Log progress for large batches
                    if ($totalCodes >= 10000 && $insertedCount % 10000 === 0) {
                        Log::info("[GeneratePromotionCodesJob] Inserted {$insertedCount} codes so far");
                    }
                }
            }

            // Generate Static code created by user
            if ($this->groupData['code_type'] === 'static') {
                $code = $this->groupData['static_code'];
                $codeQuantity = $this->groupData['total_codes'] ?? 1;

                DB::table('promotion_codes')->insert([
                    'code' => strtoupper($code),
                    'promotion_code_group_id' => $this->group->id,
                    'status' => $this->group->status,
                    'code_quantity' => $codeQuantity,
                    'used_code_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                Log::info('[GeneratePromotionCodesJob] Generated static code', [
                    'code' => $code,
                    'code_quantity' => $codeQuantity
                ]);
            }

            // get all inserted promotion codes
			// TODO: assigned too much into the variables. use 1000 per batch to get.
            $promotionCodes = PromotionCode::where('promotion_code_group_id', $this->group->id)->get();

            // prepare reward pivot data
            if ($this->groupData['discount_type'] === 'reward' && $rewardable_type && $rewardable_id) {

                $rewardPivotData = [];

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
                $insertedPivotCount = 0;
                foreach (array_chunk($rewardPivotData, $batchSize) as $batch) {
                    DB::table('promotion_code_rewardable')->insert($batch);
                    $insertedPivotCount += count($batch);

                    // Log progress for large batches
                    if (count($rewardPivotData) >= 10000 && $insertedPivotCount % 10000 === 0) {
                        Log::info("[GeneratePromotionCodesJob] Inserted {$insertedPivotCount} reward relationships so far");
                    }
                }
            }

            $logData = [
                'promotion_code_group_id' => $this->group->id,
                'total_codes' => $totalCodes,
                'discount_type' => $this->groupData['discount_type'] ?? null,
            ];

            if ($this->groupData['discount_type'] === 'reward') {
                $logData['rewardable_type'] = $rewardable_type;
                $logData['rewardable_id'] = $rewardable_id;
                $logData['quantity'] = $quantity;
            } else {
                $logData['discount_amount'] = $this->groupData['discount_amount'] ?? null;
                $logData['user_type'] = $this->groupData['user_type'] ?? null;
                // Log selected products if any
                if (!empty($this->groupData['products'] ?? [])) {
                    $logData['products'] = $this->groupData['products'];
                }
            }

            Log::info('[GeneratePromotionCodesJob] Completed generating promotion codes', $logData);
        });
    }
}
