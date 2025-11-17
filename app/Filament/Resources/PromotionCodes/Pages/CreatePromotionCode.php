<?php

namespace App\Filament\Resources\PromotionCodes\Pages;

use App\Filament\Resources\PromotionCodes\PromotionCodeResource;
use App\Models\PromotionCode;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePromotionCode extends CreateRecord
{
    protected static string $resource = PromotionCodeResource::class;
}
