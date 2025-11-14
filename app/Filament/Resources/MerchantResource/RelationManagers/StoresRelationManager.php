<?php

namespace App\Filament\Resources\MerchantResource\RelationManagers;

use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StoresRelationManager extends RelationManager
{
    protected static string $relationship = 'stores';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->url(fn ($record) => route('filament.resources.stores.edit', $record->id)),
                Tables\Columns\TextColumn::make('address'),
                Tables\Columns\TextColumn::make('manager_name'),
                Tables\Columns\TextColumn::make('address_postcode'),
                Tables\Columns\TextColumn::make('state.name'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make()
                //     ->mutateFormDataUsing(function (array $data): array {
                //         $data['user_id'] = $this->ownerRecord->user_id;
                //         return $data;
                //     }),
                // Tables\Actions\AttachAction::make()
                //     ->preloadRecordSelect()
                //     ->form(fn (Tables\Actions\AttachAction $action): array => [
                //         $action->getRecordSelect()
                //             ->label('Store')
                //             ->options(function ($livewire) {
                //                 return Store::where('user_id', $livewire->ownerRecord->user_id)
                //                     ->pluck('name', 'id');
                //             })
                //             ->required(),
                //     ])->confirm(fn ($record) => "Are you sure you want to attach this store to {$record->name}?"),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make(),
                // Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
