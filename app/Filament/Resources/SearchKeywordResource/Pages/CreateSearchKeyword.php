<?php

namespace App\Filament\Resources\SearchKeywordResource\Pages;

use App\Filament\Resources\SearchKeywordResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSearchKeyword extends CreateRecord
{
    protected static string $resource = SearchKeywordResource::class;
}
