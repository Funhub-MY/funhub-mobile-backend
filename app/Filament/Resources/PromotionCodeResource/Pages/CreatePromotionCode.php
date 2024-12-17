<?php

namespace App\Filament\Resources\PromotionCodeResource\Pages;

use App\Filament\Resources\PromotionCodeResource;
use App\Models\PromotionCode;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePromotionCode extends CreateRecord
{
    protected static string $resource = PromotionCodeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $numberOfCodes = $data['number_of_codes'];
        unset($data['number_of_codes']);

        $firstCode = null;
        
        for ($i = 0; $i < $numberOfCodes; $i++) {
            $codeData = array_merge($data, [
                'code' => PromotionCode::generateUniqueCode(),
            ]);
            
            $code = PromotionCode::create($codeData);
            
            if (!$firstCode) {
                $firstCode = $code;
            }

            // create the reward relationship
            if (isset($data['rewardable'])) {
                $code->rewardable()->associate($data['rewardable']);
                $code->save();
            }
        }

        return $firstCode;
    }
}
