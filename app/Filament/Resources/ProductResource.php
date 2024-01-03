<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Forms;
use Filament\Tables;
use App\Models\Product;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\ProductResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use App\Filament\Resources\ProductResource\RelationManagers;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Sales';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Section::make('Product Detials')
                            ->schema([
                                Forms\Components\SpatieMediaLibraryFileUpload::make('images')
                                    ->label('Images')
                                    ->multiple()
                                    ->collection(Product::MEDIA_COLLECTION_NAME)
                                    ->columnSpan('full')
                                    ->customProperties(['is_cover' => false])
                                    // disk is s3_public 
                                    ->disk(function () {
                                        if (config('filesystems.default') === 's3') {
                                            return 's3_public';
                                        }
                                    })
                                    ->acceptedFileTypes(['image/*'])
                                    ->maxFiles(20)
                                    ->hidden(fn (Closure $get) => $get('type') !== 'multimedia')
                                    ->rules('image'),

                                TextInput::make('name')
                                    ->required(),

                                Select::make('status')
                                    ->options(Product::STATUS)
                                    ->default(0)
                                    ->required(),

                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->unique()
                                    ->required(),

                                RichEditor::make('description')
                                    ->required(),
                            ])
                    ])->columnSpan(['lg' => 2]),
                Forms\Components\Group::make()
                    ->schema([
                        Section::make('Product Pricing & Inventory')
                            ->schema([
                                Fieldset::make('Pricing')
                                    ->schema([
                                        Forms\Components\TextInput::make('unit_price')
                                            ->label('Unit Price')
                                            ->required()
                                            ->numeric()
                                            ->prefix('RM')
                                            ->mask(fn (Forms\Components\TextInput\Mask $mask) => $mask
                                                ->numeric()
                                                ->decimalPlaces(2)
                                                ->minValue(1)
                                                ->thousandsSeparator(','),
                                            ),
                                        Forms\Components\TextInput::make('discount_price')
                                            ->label('Discounted Unit Price')
                                            ->required()
                                            ->numeric()
                                            ->prefix('RM')
                                            ->mask(fn (Forms\Components\TextInput\Mask $mask) => $mask
                                                ->numeric()
                                                ->decimalPlaces(2)
                                                ->minValue(1)
                                                ->thousandsSeparator(','),
                                            ),
                                    ]),
                                    Checkbox::make('unlimited_supply')
                                        ->reactive()
                                        ->label('Keep Selling After Inventory Quantity Depleted')
                                        ->default(false),

                                    TextInput::make('quantity')
                                        ->label('Current Quantity Available')
                                        ->default(0)
                                        ->numeric()
                            ])
                ])->columnSpan(['lg' => 2]),
                // Forms\Components\Group::make()
                // ->schema([
                //     Forms\Components\Section::make('Rewards')->schema([
                //         Forms\Components\Select::make('rewards')
                //         ->relationship('rewards', 'name')
                //         ->label('')
                //         ->multiple()
                //         ->searchable()
                //         ->placeholder('Select rewards...'),
                // ])->columns(1),
            ])->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sku')
                    ->searchable()
                    ->label('SKU'),
                TextColumn::make('unit_price')
                    ->sortable()
                    ->prefix('RM'),
                TextColumn::make('discount_price')
                    ->sortable()
                    ->prefix('RM'),
                TextColumn::make('quantity'),
                Tables\Columns\BadgeColumn::make('status')
                ->enum(Product::STATUS)
                ->colors([
                    'secondary' => 0,
                    'success' => 1,
                ])
                ->sortable()
                ->searchable(),
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
            RelationManagers\RewardsRelationManager::class,
            AuditsRelationManager::class,
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }    
}
