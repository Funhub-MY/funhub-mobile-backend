<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleStoreCategoryResource\Pages;
use App\Filament\Resources\ArticleStoreCategoryResource\RelationManagers;
use App\Models\ArticleStoreCategory;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ArticleStoreCategoryResource extends Resource
{
    protected static ?string $model = ArticleStoreCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

	protected static ?string $navigationGroup = 'Merchant';

	protected static ?int $navigationSort = 5;

	public static function form(Form $form): Form
    {
        return $form
            ->schema([
				Select::make('article_category_id')
					->label('Article Category')
					->relationship('articleCategory', 'name')
					->searchable()
					->required(),
				Select::make('merchant_category_id')
					->label('Store Category')
					->relationship('merchantCategory', 'name')
					->searchable()
					->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
				TextColumn::make('articleCategory.name')->label('Article Category')->sortable()->searchable(),
				TextColumn::make('merchantCategory.name')->label('Store Category')->sortable()->searchable(),
				TextColumn::make('created_at')->label('Created At')->dateTime(),
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
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticleStoreCategories::route('/'),
            'create' => Pages\CreateArticleStoreCategory::route('/create'),
            'edit' => Pages\EditArticleStoreCategory::route('/{record}/edit'),
        ];
    }    
}
