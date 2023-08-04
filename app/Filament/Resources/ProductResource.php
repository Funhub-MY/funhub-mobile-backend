<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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

                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->required(),

                                RichEditor::make('description')

                                    ->required(),
                            ])
                    ])->columnSpan(['lg' => 2]),
                Forms\Components\Group::make()
                    ->schema([
                        Section::make('Product Pricing and Inventory')
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
                                        ->numeric()
                            ])
                ])->columnSpan(['lg' => 2]),
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
            //
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
