<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    // protected function mutateFormDataBeforeCreate(array $data): array
    // {
    //     //dd($data);
    //     return $data;
    // }
}
