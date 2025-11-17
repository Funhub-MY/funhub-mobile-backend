<?php

namespace App\Filament\Resources\Merchants\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use App\Models\Store;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StoresRelationManager extends RelationManager
{
    protected static string $relationship = 'stores';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->url(fn ($record) => route('filament.admin.resources.stores.edit', $record->id)),
                TextColumn::make('address'),
                TextColumn::make('manager_name'),
                TextColumn::make('address_postcode'),
                TextColumn::make('state.name'),
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
            ->recordActions([
                // Tables\Actions\EditAction::make(),
                DetachAction::make(),
            ])
            ->toolbarActions([
                DetachBulkAction::make(),
                // Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
