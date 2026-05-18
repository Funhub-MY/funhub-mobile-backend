<?php

namespace App\Filament\Resources\PromotionCodeGroupResource\Pages;

use App\Filament\Resources\PromotionCodeGroupResource;
use App\Jobs\GeneratePromotionCodesJob;
use App\Models\PromotionCodeGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreatePromotionCodeGroup extends CreateRecord
{
    protected static string $resource = PromotionCodeGroupResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        Log::info('[PromotionCodeGroup] Creating promotion code group', $data);

        return DB::transaction(function () use ($data) {
            $jobData = $data;

            $totalCodes = ($data['code_type'] ?? null) === 'random'
                ? (int) ($data['total_codes'] ?? 1)
                : 1;

            $products = $data['products'] ?? [];
            $paymentMethods = $data['paymentMethods'] ?? [];

            $groupAttributes = collect($data)->except([
                'static_code',
                'products',
                'paymentMethods',
                'rewardable_type',
                'rewardable_id',
                'quantity',
            ])->toArray();

            $groupAttributes['use_fix_amount_discount'] = ($groupAttributes['discount_type'] ?? null) === 'fix_amount';

            $group = static::getModel()::create($groupAttributes);

            if (! empty($products)) {
                $group->products()->sync($products);
            }

            if (! empty($paymentMethods)) {
                $group->paymentMethods()->sync($paymentMethods);
            }

            GeneratePromotionCodesJob::dispatchSync($group, $jobData);

            Notification::make()
                ->title('Promotion Code Group Created')
                ->body("The promotion code group has been created with {$totalCodes} code(s).")
                ->success()
                ->send();

            Log::info('[PromotionCodeGroup] Generated promotion codes', [
                'promotion_code_group_id' => $group->id,
                'total_codes' => $totalCodes,
            ]);

            return $group;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
