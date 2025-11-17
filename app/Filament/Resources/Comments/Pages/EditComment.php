<?php

namespace App\Filament\Resources\Comments\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Comments\CommentResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditComment extends EditRecord
{
    protected static string $resource = CommentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
