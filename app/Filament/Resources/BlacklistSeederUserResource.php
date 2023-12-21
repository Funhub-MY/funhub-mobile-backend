<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlacklistSeederUserResource\Pages;
use App\Filament\Resources\BlacklistSeederUserResource\RelationManagers;
use App\Models\BlacklistSeederUser;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;

class BlacklistSeederUserResource extends Resource
{
    protected static ?string $model = BlacklistSeederUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('user_id')
                    ->label('User')
                    ->options(User::all()->pluck('email', 'id')) //use email instead of name because email is unique
                    ->searchable()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.email')
                    ->label('User'),
                Tables\Columns\TextColumn::make('created_at'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListBlacklistSeederUsers::route('/'),
            'create' => Pages\CreateBlacklistSeederUser::route('/create'),
            'edit' => Pages\EditBlacklistSeederUser::route('/{record}/edit'),
        ];
    }    
}
