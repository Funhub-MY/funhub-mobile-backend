<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserHistoricalLocationResource\Pages;
use App\Filament\Resources\UserHistoricalLocationResource\RelationManagers;
use App\Jobs\PopulateLocationAddressForUser;
use App\Models\User;
use App\Models\UserHistoricalLocation;
use Closure;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class UserHistoricalLocationResource extends Resource
{
    protected static ?string $model = UserHistoricalLocation::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    // disable create and edit
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    protected static ?string $navigationGroup = 'Users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recorded On')
                    ->dateTime('d/m/Y h:iA'),
                Tables\Columns\TextColumn::make('user.name')->label('User')
                    ->url(fn ($record) => route('filament.resources.users.view', $record->user))
                    ->openUrlInNewTab()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('lat'),
                Tables\Columns\TextColumn::make('lng'),
                Tables\Columns\TextColumn::make('address')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('address_2')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('zip_code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('state')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                // filter by available cities
                Tables\Filters\SelectFilter::make('city')
                    ->label('City')
                    ->options(function () {
                        return UserHistoricalLocation::select('city')
                            ->distinct()
                            ->orderBy('city')
                            ->get()
                            ->pluck('city', 'city');
                    })
                    ->searchable(),

                Tables\Filters\SelectFilter::make('state')
                    ->label('State')
                    ->options(function () {
                        return UserHistoricalLocation::select('state')
                            ->distinct()
                            ->orderBy('state')
                            ->get()
                            ->pluck('state', 'state');
                    })
                    ->searchable(),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // trigger populate location address for user
                Tables\Actions\BulkAction::make('populate-location-address-for-user')
                    ->label('Populate Location Address for User')
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            dispatch(new PopulateLocationAddressForUser($record));
                        }

                        Notification::make()
                            ->success()
                            ->title('Queued for process')
                            ->body('User locations are queued for process address from Google, please check back in 10min')
                            ->send();
                    })
                    ->requiresConfirmation(),
                // Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // filter by available states
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserHistoricalLocations::route('/'),
            'create' => Pages\CreateUserHistoricalLocation::route('/create'),
            'edit' => Pages\EditUserHistoricalLocation::route('/{record}/edit'),
        ];
    }
}
