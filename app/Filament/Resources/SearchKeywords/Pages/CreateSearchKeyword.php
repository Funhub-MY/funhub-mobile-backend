<?php

namespace App\Filament\Resources\SearchKeywords\Pages;

use App\Filament\Resources\SearchKeywords\SearchKeywordResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSearchKeyword extends CreateRecord
{
    protected static string $resource = SearchKeywordResource::class;
}
