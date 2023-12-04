<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use Filament\Pages\Actions;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\ArticleResource;
use App\Models\Article;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);

        // link relation for subCategories
        if ($data['sub_categories']) {
            // only detach categories with parent_id (detaches all sub categories)
            $record->categories->each(function ($category) use ($record) {
                if ($category->parent_id) {
                    $record->categories()->detach($category->id);
                }
            });
            $record->subCategories()->attach($data['sub_categories'], ['created_at' => now(), 'updated_at' => now()]);
        }

        if ($data['locations']) {
            $record->location()->sync($data['locations']);
        }
        
        return $record;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['sub_categories'] = $this->record->subCategories->pluck('id')->toArray();
        $data['locations'] = $this->record->location->pluck('id')->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

}
