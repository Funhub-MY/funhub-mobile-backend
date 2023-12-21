<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AutoArchiveKeywordResource\Pages;
use App\Filament\Resources\AutoArchiveKeywordResource\RelationManagers;
use App\Models\AutoArchiveKeyword;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

class AutoArchiveKeywordResource extends Resource
{

    protected static ?string $navigationGroup = 'Articles';

    protected static ?int $navigationSort = 4;

    protected static ?string $model = AutoArchiveKeyword::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('keyword')
                ->autofocus()
                ->required()
                ->unique(ignoreRecord:true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('keyword')
                ->searchable()
                ->sortable(),
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
            'index' => Pages\ListAutoArchiveKeywords::route('/'),
            'create' => Pages\CreateAutoArchiveKeyword::route('/create'),
            'edit' => Pages\EditAutoArchiveKeyword::route('/{record}/edit'),
        ];
    }
}
