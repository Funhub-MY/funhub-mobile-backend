<?php

namespace App\Filament\Resources\FailedStoreImports;

use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use App\Filament\Resources\FailedStoreImports\Pages\ListFailedStoreImports;
use App\Filament\Resources\FailedStoreImportResource\Pages;
use App\Filament\Resources\FailedStoreImportResource\RelationManagers;
use App\Models\FailedStoreImport;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;

class FailedStoreImportResource extends Resource
{
    protected static ?string $model = FailedStoreImport::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-exclamation-circle';
    
    protected static ?string $navigationLabel = 'Failed Store Imports';
    
    protected static string | \UnitEnum | null $navigationGroup = 'Merchant';
    
    protected static ?int $navigationSort = 2;
    
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
    
    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('Store Name')
                            ->disabled(),
                        TextInput::make('address')
                            ->disabled(),
                        TextInput::make('address_postcode')
                            ->label('Postcode')
                            ->disabled(),
                        TextInput::make('city')
                            ->disabled(),
                        TextInput::make('state_id')
                            ->label('State ID')
                            ->disabled(),
                        TextInput::make('country_id')
                            ->label('Country ID')
                            ->disabled(),
                        TextInput::make('business_phone_no')
                            ->label('Phone Number')
                            ->disabled(),
                        Textarea::make('business_hours')
                            ->disabled(),
                        Textarea::make('rest_hours')
                            ->disabled(),
                        Toggle::make('is_appointment_only')
                            ->disabled(),
                        TextInput::make('user_id')
                            ->disabled(),
                        TextInput::make('merchant_id')
                            ->disabled(),
                        TextInput::make('google_place_id')
                            ->disabled(),
                        TextInput::make('lang')
                            ->label('Latitude')
                            ->disabled(),
                        TextInput::make('long')
                            ->label('Longitude')
                            ->disabled(),
                        Textarea::make('parent_categories')
                            ->disabled(),
                        Textarea::make('sub_categories')
                            ->disabled(),
                        Toggle::make('is_hq')
                            ->label('Is HQ')
                            ->disabled(),
                        Textarea::make('failure_reason')
                            ->disabled(),
                        Textarea::make('original_data')
                            ->disabled(),
                    ])
                    ->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Store Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('address')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('lang')
                    ->label('Latitude'),
                TextColumn::make('long')
                    ->label('Longitude'),
                TextColumn::make('failure_reason')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([])
            ->toolbarActions([
                ExportBulkAction::make()
                    ->label('Export to CSV')
                    ->exports([
                        ExcelExport::make()
                            ->withFilename('failed-store-imports-' . now()->format('Y-m-d'))
                            ->withColumns([
                                Column::make('id')->heading('ID'),
                                Column::make('name')->heading('Store Name'),
                                Column::make('address')->heading('Address'),
                                Column::make('address_postcode')->heading('Postcode'),
                                Column::make('city')->heading('City'),
                                Column::make('state_id')->heading('State ID'),
                                Column::make('country_id')->heading('Country ID'),
                                Column::make('business_phone_no')->heading('Phone Number'),
                                Column::make('lang')->heading('Latitude'),
                                Column::make('long')->heading('Longitude'),
                                Column::make('google_place_id')->heading('Google Place ID'),
                                Column::make('merchant_id')->heading('Merchant ID'),
                                Column::make('user_id')->heading('User ID'),
                                Column::make('is_hq')->heading('Is HQ')
                                    ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                                Column::make('is_appointment_only')->heading('Is Appointment Only')
                                    ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                                Column::make('parent_categories')->heading('Parent Categories'),
                                Column::make('sub_categories')->heading('Sub Categories'),
                                Column::make('failure_reason')->heading('Failure Reason'),
                                Column::make('created_at')->heading('Created At')
                                    ->formatStateUsing(fn ($state) => $state ? $state->format('Y-m-d H:i:s') : ''),
                            ])
                    ])
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => ListFailedStoreImports::route('/'),
            // 'view' => Pages\ViewFailedStoreImport::route('/{record}'),
        ];
    }    
}
