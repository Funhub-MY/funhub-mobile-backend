<?php

namespace App\Filament\Resources\PromotionCodeResource\Pages;

use App\Filament\Resources\PromotionCodeResource;
use App\Models\PromotionCode;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePromotionCode extends CreateRecord
{
    protected static string $resource = PromotionCodeResource::class;
}
