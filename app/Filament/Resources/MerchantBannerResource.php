<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Form;
use Filament\Resources\Table;
use App\Models\MerchantBanner;
use Filament\Resources\Resource;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\MerchantBannerResource\Pages;

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
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('link_to')
                        ->required()
                        ->maxLength(255),
                    Select::make('status')
                        ->options(options: MerchantBanner::STATUS)
                        ->default(MerchantBanner::STATUS_DRAFT)
                        ->required(),
                    SpatieMediaLibraryFileUpload::make('banner')
                        ->image()
                        ->collection(MerchantBanner::MEDIA_COLLECTION_NAME)
                        ->required()
                        ->disk(function () {
                            if (config('filesystems.default') === 's3') {
                                return 's3_public';
                            }
                            return config('filesystems.default');
                        })
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('link_to'),
                TextColumn::make('status')
                    ->formatStateUsing(fn ($state) => MerchantBanner::STATUS[$state] ?? ''),
                TextColumn::make('created_at')
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
            'index' => Pages\ListMerchantBanners::route('/'),
            'create' => Pages\CreateMerchantBanner::route('/create'),
            'edit' => Pages\EditMerchantBanner::route('/{record}/edit'),
        ];
    }
}
