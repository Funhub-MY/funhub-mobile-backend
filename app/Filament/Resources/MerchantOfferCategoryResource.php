<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Str;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Card;
use App\Models\MerchantOfferCategory;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\MerchantOfferCategoryResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\MerchantOfferCategoryResource\RelationManagers;
use Filament\Forms\Components\Group;

class MerchantOfferCategoryResource extends Resource
{
    protected static ?string $model = MerchantOfferCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Merchant';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Card::make([
                    TextInput::make('name')
                        ->label('Category Name')
                        ->autofocus()
                        ->required()
                        ->unique()
                        ->lazy()
                        ->afterStateUpdated(fn (string $context, $state, callable $set) => $context === 'create' ? $set('slug', Str::slug($state)) : null)
                        ->columnSpanFull(),

                    RichEditor::make('description')
                        ->columnSpanFull(),

                    TextInput::make('slug')
                        ->required()
                        ->unique(MerchantOfferCategory::class, 'slug', ignoreRecord: true)
                        ->columnSpanFull(),

                    Select::make('parent_id')
                        ->label('Parent Category')
                        ->relationship('parent', 'name')
                        ->preload()
                        ->nullable(),

                    Group::make()
                        ->schema([
                            Toggle::make('is_featured')
                                ->label('Is Featured On Homepage?'),
    
                            Toggle::make('is_active')
                                ->label('Is Active?'),
                        ]),                   

                    Forms\Components\SpatieMediaLibraryFileUpload::make('icon')
                        ->label('Icon')
                        ->collection('merchant_offer_category')
                        ->disk(function () {
                            if (config('filesystems.default') === 's3') {
                                return 's3_public';
                            }
                        })
                        ->acceptedFileTypes(['image/*'])
                        ->rules('image')
                        ->columnSpanFull(),

                    Hidden::make('user_id')
                        ->default(fn() => auth()->id()),
                ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),

                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('parent.name')
                    ->label('Parent Category')
                    ->sortable()
                    ->searchable(),

                ToggleColumn::make('is_featured')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->sortable(),

                TextColumn::make('description')
                    ->html(),

                TextColumn::make('user.name')
                    ->label('Created By')
                    ->sortable(),

                TextColumn::make('created_at')
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
            'index' => Pages\ListMerchantOfferCategories::route('/'),
            'create' => Pages\CreateMerchantOfferCategory::route('/create'),
            'edit' => Pages\EditMerchantOfferCategory::route('/{record}/edit'),
        ];
    }
}
