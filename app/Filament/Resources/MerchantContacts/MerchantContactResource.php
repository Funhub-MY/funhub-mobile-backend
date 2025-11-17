<?php

namespace App\Filament\Resources\MerchantContacts;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use App\Filament\Resources\MerchantContacts\Pages\ListMerchantContacts;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\MerchantContact;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\MerchantContactResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\MerchantContactResource\RelationManagers;

class MerchantContactResource extends Resource
{
    protected static ?string $model = MerchantContact::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = 'Merchant Contacts';

    protected static string | \UnitEnum | null $navigationGroup = 'Merchant';

    protected static ?int $navigationSort = 6;

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
                TextColumn::make('id')->sortable()->searchable(),
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('email')->sortable()->searchable(),
                TextColumn::make('tel_no')
                    ->label('Phone No')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('company_name')->label('Company Name')->sortable()->searchable(),
                TextColumn::make('business_type')->label('Business Type')->sortable()->searchable(),
                TextColumn::make('message_type')->label('Message Type')->sortable()->searchable(),
                TextColumn::make('message')->sortable()->searchable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d/m/Y H:ia')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                
            ])
            ->toolbarActions([
                
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            AuditsRelationManager::class,
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => ListMerchantContacts::route('/'),
            //'create' => Pages\CreateMerchantContact::route('/create'),
            //'edit' => Pages\EditMerchantContact::route('/{record}/edit'),
        ];
    }    
}
