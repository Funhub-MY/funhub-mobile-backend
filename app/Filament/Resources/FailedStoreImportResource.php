<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FailedStoreImportResource\Pages;
use App\Filament\Resources\FailedStoreImportResource\RelationManagers;
use App\Models\FailedStoreImport;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
                Forms\Components\Card::make()
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
            ->bulkActions([]);
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
