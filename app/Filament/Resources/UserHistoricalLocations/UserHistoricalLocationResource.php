<?php

namespace App\Filament\Resources\UserHistoricalLocations;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\BulkAction;
use Maatwebsite\Excel\Excel;
use App\Filament\Resources\UserHistoricalLocations\Pages\ListUserHistoricalLocations;
use App\Filament\Resources\UserHistoricalLocations\Pages\CreateUserHistoricalLocation;
use App\Filament\Resources\UserHistoricalLocations\Pages\EditUserHistoricalLocation;
use App\Filament\Resources\UserHistoricalLocationResource\Pages;
use App\Filament\Resources\UserHistoricalLocationResource\RelationManagers;
use App\Jobs\PopulateLocationAddressForUser;
use App\Models\User;
use App\Models\UserHistoricalLocation;
use Closure;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class UserHistoricalLocationResource extends Resource
{
    protected static ?string $model = UserHistoricalLocation::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

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

    protected static string | \UnitEnum | null $navigationGroup = 'Users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Recorded On')
                    ->dateTime('d/m/Y h:iA'),
                TextColumn::make('user.name')->label('User')
                    ->url(fn ($record) => route('filament.admin.resources.users.view', $record->user))
                    ->openUrlInNewTab()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('lat'),
                TextColumn::make('lng'),
                TextColumn::make('address')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('address_2')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('zip_code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('state')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                // filter by available cities
                SelectFilter::make('city')
                    ->label('City')
                    ->options(function () {
                        return UserHistoricalLocation::select('city')
                            ->distinct()
                            ->orderBy('city')
                            ->get()
                            ->pluck('city', 'city');
                    })
                    ->searchable(),

                SelectFilter::make('state')
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
            ->recordActions([
                // Tables\Actions\EditAction::make(),
            ])
            ->toolbarActions([
                // trigger populate location address for user
                BulkAction::make('populate-location-address-for-user')
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
				ExportBulkAction::make()->exports([
					ExcelExport::make('user_historical_locations')
						->withColumns([
							Column::make('User ID')
								->getStateUsing(fn ($record) => $record->user->id),
							Column::make('User Name')
								->getStateUsing(fn ($record) => $record->user->name),
							Column::make('Age')
								->getStateUsing(fn ($record) => $record->user->dob ? now()->diffInYears($record->user->dob) : null),
							Column::make('Gender')
								->getStateUsing(fn ($record) => $record->user->gender ?? null),
							Column::make('Latitude')
								->getStateUsing(fn ($record) => $record->lat),
							Column::make('Longitude')
								->getStateUsing(fn ($record) => $record->lng),
							Column::make('Address')
								->getStateUsing(fn ($record) => $record->address),
							Column::make('Address 2')
								->getStateUsing(fn ($record) => $record->address_2),
							Column::make('ZipCode')
								->getStateUsing(fn ($record) => $record->zip_code),
							Column::make('City')
								->getStateUsing(fn ($record) => $record->city),
							Column::make('State')
								->getStateUsing(fn ($record) => $record->state),
							Column::make('Country')
								->getStateUsing(fn ($record) => $record->country),
						])
						->withChunkSize(500)
						->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
						->withWriterType(Excel::CSV),
				]),
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
            'index' => ListUserHistoricalLocations::route('/'),
            'create' => CreateUserHistoricalLocation::route('/create'),
            'edit' => EditUserHistoricalLocation::route('/{record}/edit'),
        ];
    }
}
