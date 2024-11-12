<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateApplication extends CreateRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;
        $key = $record->createToken($record->name);

        $record->api_key = $key->plainTextToken;
        $record->save();

        Log::info('[CreateApplication] Application created successfully!', [
            'application' => $record->name
        ]);
    }
}
