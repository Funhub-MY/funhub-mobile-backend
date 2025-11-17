<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
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
