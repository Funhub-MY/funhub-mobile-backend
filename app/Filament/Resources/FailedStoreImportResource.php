<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FailedStoreImportResource\Pages;
use App\Filament\Resources\FailedStoreImportResource\RelationManagers;
use App\Models\FailedStoreImport;
use Filament\Forms;
use Filament\Forms\Form;
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

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';
    
    protected static ?string $navigationLabel = 'Failed Store Imports';
    
    protected static ?string $navigationGroup = 'Merchant';
    
    protected static ?int $navigationSort = 2;
    
    public static function canCreate(): bool
    {
        return false;
    }
    
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
    
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
    
    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Store Name')
                            ->disabled(),
                        Forms\Components\TextInput::make('address')
                            ->disabled(),
                        Forms\Components\TextInput::make('address_postcode')
                            ->label('Postcode')
                            ->disabled(),
                        Forms\Components\TextInput::make('city')
                            ->disabled(),
                        Forms\Components\TextInput::make('state_id')
                            ->label('State ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('country_id')
                            ->label('Country ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('business_phone_no')
                            ->label('Phone Number')
                            ->disabled(),
                        Forms\Components\Textarea::make('business_hours')
                            ->disabled(),
                        Forms\Components\Textarea::make('rest_hours')
                            ->disabled(),
                        Forms\Components\Toggle::make('is_appointment_only')
                            ->disabled(),
                        Forms\Components\TextInput::make('user_id')
                            ->disabled(),
                        Forms\Components\TextInput::make('merchant_id')
                            ->disabled(),
                        Forms\Components\TextInput::make('google_place_id')
                            ->disabled(),
                        Forms\Components\TextInput::make('lang')
                            ->label('Latitude')
                            ->disabled(),
                        Forms\Components\TextInput::make('long')
                            ->label('Longitude')
                            ->disabled(),
                        Forms\Components\Textarea::make('parent_categories')
                            ->disabled(),
                        Forms\Components\Textarea::make('sub_categories')
                            ->disabled(),
                        Forms\Components\Toggle::make('is_hq')
                            ->label('Is HQ')
                            ->disabled(),
                        Forms\Components\Textarea::make('failure_reason')
                            ->disabled(),
                        Forms\Components\Textarea::make('original_data')
                            ->disabled(),
                    ])
                    ->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Store Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('address')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lang')
                    ->label('Latitude'),
                Tables\Columns\TextColumn::make('long')
                    ->label('Longitude'),
                Tables\Columns\TextColumn::make('failure_reason')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([])
            ->bulkActions([
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
            'index' => Pages\ListFailedStoreImports::route('/'),
            // 'view' => Pages\ViewFailedStoreImport::route('/{record}'),
        ];
    }    
}
