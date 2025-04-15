<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Resources\Table;
use App\Models\MerchantBanner;

use Filament\Forms\Components\Card;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\MerchantBannerResource\Pages;
use Illuminate\Validation\Rules\Unique;

class MerchantBannerResource extends Resource
{
    protected static ?string $model = MerchantBanner::class;

    protected static ?string $navigationIcon = 'heroicon-o-photograph';

    protected static ?string $navigationGroup = 'Merchant';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Card::make()->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('link_to')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('status')
                        ->options(options: MerchantBanner::STATUS)
                        ->default(MerchantBanner::STATUS_DRAFT)
                        ->required(),
                    Forms\Components\SpatieMediaLibraryFileUpload::make('banner')
                        ->image()
                        ->collection(MerchantBanner::MEDIA_COLLECTION_NAME)
                        ->required()
                        ->disk(function () {
                            if (config('filesystems.default') === 's3') {
                                return 's3_public';
                            }
                            return config('filesystems.default');
                        }),
                    Forms\Components\TextInput::make('order')
                        ->label('Order')
                        ->numeric()
                        ->required()
                        ->default(fn ($context): int => $context === 'create' ? (MerchantBanner::query()->max('order') ?? 0) + 1 : 0)
                        ->unique(ignoreRecord: true), // Add unique rule, ignoring current record on edit
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('link_to'),
                Tables\Columns\TextColumn::make('status')
                    ->formatStateUsing(fn ($state) => MerchantBanner::STATUS[$state] ?? ''),
                Tables\Columns\TextColumn::make('order')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('order', 'asc'); // Set default sort
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
            'index' => Pages\ListMerchantBanners::route('/'),
            'create' => Pages\CreateMerchantBanner::route('/create'),
            'edit' => Pages\EditMerchantBanner::route('/{record}/edit'),
        ];
    }
}
