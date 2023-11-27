<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MerchantContactResource\Pages;
use App\Filament\Resources\MerchantContactResource\RelationManagers;
use App\Models\MerchantContact;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MerchantContactResource extends Resource
{
    protected static ?string $model = MerchantContact::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $modelLabel = 'Merchant Contacts';

    protected static ?string $navigationGroup = 'Merchant';

    protected static ?int $navigationSort = 6;

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
                Tables\Columns\TextColumn::make('id')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('email')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('tel_no')
                    ->label('Phone No')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('company_name')->label('Company Name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('business_type')->label('Business Type')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('message_type')->label('Message Type')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('message')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d/m/Y H:ia')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                
            ])
            ->bulkActions([
                
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
            'index' => Pages\ListMerchantContacts::route('/'),
            //'create' => Pages\CreateMerchantContact::route('/create'),
            //'edit' => Pages\EditMerchantContact::route('/{record}/edit'),
        ];
    }    
}
