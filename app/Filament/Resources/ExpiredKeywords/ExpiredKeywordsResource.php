<?php

namespace App\Filament\Resources\ExpiredKeywords;

use Filament\Schemas\Schema;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ExpiredKeywords\Pages\ListExpiredKeywords;
use App\Filament\Resources\ExpiredKeywords\Pages\CreateExpiredKeywords;
use App\Filament\Resources\ExpiredKeywords\Pages\EditExpiredKeywords;
use App\Filament\Resources\ExpiredKeywordsResource\Pages;
use App\Filament\Resources\ExpiredKeywordsResource\RelationManagers;
use App\Models\ExpiredKeyword;
use App\Models\ExpiredKeywords;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ExpiredKeywordsResource extends Resource
{
    protected static ?string $model = ExpiredKeyword::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

	protected static string | \UnitEnum | null $navigationGroup = 'Articles';

	protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
				TextInput::make('keyword')
					->required()
					->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
				TextColumn::make('keyword')
					->searchable()
					->sortable(),
				TextColumn::make('created_at')
					->dateTime()
					->sortable(),
				TextColumn::make('updated_at')
					->dateTime()
					->sortable(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpiredKeywords::route('/'),
            'create' => CreateExpiredKeywords::route('/create'),
            'edit' => EditExpiredKeywords::route('/{record}/edit'),
        ];
    }
}
