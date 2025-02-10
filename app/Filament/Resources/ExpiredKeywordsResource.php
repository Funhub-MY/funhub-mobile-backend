<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpiredKeywordsResource\Pages;
use App\Filament\Resources\ExpiredKeywordsResource\RelationManagers;
use App\Models\ExpiredKeyword;
use App\Models\ExpiredKeywords;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ExpiredKeywordsResource extends Resource
{
    protected static ?string $model = ExpiredKeyword::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

	protected static ?string $navigationGroup = 'Articles';

	protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpiredKeywords::route('/'),
            'create' => Pages\CreateExpiredKeywords::route('/create'),
            'edit' => Pages\EditExpiredKeywords::route('/{record}/edit'),
        ];
    }
}
