<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MerchantCategoryResource\Pages;
use App\Filament\Resources\MerchantCategoryResource\RelationManagers;
use App\Models\MerchantCategory;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class MerchantCategoryResource extends Resource
{
    protected static ?string $model = MerchantCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Merchant';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->lazy()
                    ->afterStateUpdated(fn (string $context, $state, callable $set) => $context === 'create' ? $set('slug', Str::slug($state)) : null),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(MerchantCategory::class, 'slug', ignoreRecord: true),
                Forms\Components\SpatieMediaLibraryFileUpload::make('image')
                    ->collection('merchant_category_cover')
                    ->customProperties(['is_cover' => true])
                    ->columnSpan('full')
                    ->maxFiles(1)
                    ->rules('image'),
                Forms\Components\RichEditor::make('description')
                    ->columnSpan('full'),
                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                  // Tables\Columns\SpatieMediaLibraryImageColumn::make('image')->collection('article_category_cover')->label('Image'),
                  Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                  Tables\Columns\TextColumn::make('description')->sortable()->searchable()->html(),
                  Tables\Columns\TextColumn::make('user.name')->label('Created By')
                      ->sortable()->searchable(),
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
            'index' => Pages\ListMerchantCategories::route('/'),
            'create' => Pages\CreateMerchantCategory::route('/create'),
            'edit' => Pages\EditMerchantCategory::route('/{record}/edit'),
        ];
    }    
}
