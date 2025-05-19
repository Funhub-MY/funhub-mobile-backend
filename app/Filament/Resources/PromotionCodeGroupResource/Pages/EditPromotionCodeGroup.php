<?php

namespace App\Filament\Resources\PromotionCodeGroupResource\Pages;

use App\Filament\Resources\PromotionCodeGroupResource;
use App\Models\PromotionCode;
use App\Models\Reward;
use App\Models\RewardComponent;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class EditPromotionCodeGroup extends EditRecord
{
    protected static string $resource = PromotionCodeGroupResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $promotionCodeGroupId = $this->record->id;

        $promotionCode = PromotionCode::where('promotion_code_group_id', $promotionCodeGroupId)
            ->first();

        if ($promotionCode) {
            $rewardableData = DB::table('promotion_code_rewardable')
                ->where('promotion_code_id', $promotionCode->id)
                ->first();

            if ($rewardableData) {
                $data['rewardable_type'] = $rewardableData->rewardable_type;
                $data['rewardable_id'] = $rewardableData->rewardable_id;
                $data['quantity'] = $rewardableData->quantity;
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // check if status was changed
        if ($this->record->wasChanged('status')) {
            // update all promotion codes under this group
            PromotionCode::where('promotion_code_group_id', $this->record->id)
                ->update(['status' => $this->record->status]);
            
            Notification::make()
                ->title('Promotion codes status updated')
                ->body('All promotion codes in this group have been ' . ($this->record->status ? 'activated' : 'deactivated'))
                ->success()
                ->send();
        }
    }
}
