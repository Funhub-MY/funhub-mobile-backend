<?php

namespace App\Filament\Resources\PromotionCodeResource\Pages;

use App\Filament\Resources\PromotionCodeResource;
use App\Models\PromotionCode;
use App\Models\Reward;
use App\Models\RewardComponent;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListPromotionCodes extends ListRecords
{
    protected static string $resource = PromotionCodeResource::class;
}
