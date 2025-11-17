<?php

namespace App\Filament\Resources\ExpiredKeywords\Pages;

use App\Filament\Resources\ExpiredKeywords\ExpiredKeywordsResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateExpiredKeywords extends CreateRecord
{
    protected static string $resource = ExpiredKeywordsResource::class;
}
