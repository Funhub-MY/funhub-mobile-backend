<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use App\Models\AutoArchiveKeyword;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\AutoArchiveKeywordResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\AutoArchiveKeywordResource\RelationManagers;

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
            AuditsRelationManager::class,
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
