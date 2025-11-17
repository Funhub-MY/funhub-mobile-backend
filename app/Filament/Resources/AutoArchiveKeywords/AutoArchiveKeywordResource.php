<?php

namespace App\Filament\Resources\AutoArchiveKeywords;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\AutoArchiveKeywords\Pages\ListAutoArchiveKeywords;
use App\Filament\Resources\AutoArchiveKeywords\Pages\CreateAutoArchiveKeyword;
use App\Filament\Resources\AutoArchiveKeywords\Pages\EditAutoArchiveKeyword;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
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

    protected static string | \UnitEnum | null $navigationGroup = 'Articles';

    protected static ?int $navigationSort = 4;

    protected static ?string $model = AutoArchiveKeyword::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
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
            'index' => ListAutoArchiveKeywords::route('/'),
            'create' => CreateAutoArchiveKeyword::route('/create'),
            'edit' => EditAutoArchiveKeyword::route('/{record}/edit'),
        ];
    }
}
