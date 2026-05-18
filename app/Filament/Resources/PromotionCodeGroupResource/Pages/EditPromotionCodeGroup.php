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
use Illuminate\Validation\ValidationException;

class EditPromotionCodeGroup extends EditRecord
{
    protected static string $resource = PromotionCodeGroupResource::class;

    protected ?string $pendingStaticCode = null;

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

		if ($this->record->code_type == 'static') {
			$data['static_code'] = $promotionCode?->code ?? '';
		}

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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('static_code', $data)) {
            $this->pendingStaticCode = $data['static_code'];
            unset($data['static_code']);

            if ($this->record->hasUserRedemptions()) {
                $currentCode = PromotionCode::where('promotion_code_group_id', $this->record->id)
                    ->value('code');

                if ($currentCode && strtoupper($this->pendingStaticCode) !== strtoupper($currentCode)) {
                    throw ValidationException::withMessages([
                        'static_code' => 'This promo code cannot be changed because it has already been redeemed by users.',
                    ]);
                }
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (
            $this->record->code_type === 'static'
            && filled($this->pendingStaticCode)
            && ! $this->record->hasUserRedemptions()
        ) {
            PromotionCode::where('promotion_code_group_id', $this->record->id)
                ->update(['code' => strtoupper($this->pendingStaticCode)]);
        }

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
