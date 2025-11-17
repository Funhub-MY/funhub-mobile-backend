<?php

namespace App\Filament\Resources\MerchantBanners;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\MerchantBanners\Pages\ListMerchantBanners;
use App\Filament\Resources\MerchantBanners\Pages\CreateMerchantBanner;
use App\Filament\Resources\MerchantBanners\Pages\EditMerchantBanner;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\MerchantBanner;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-photo';

    protected static string | \UnitEnum | null $navigationGroup = 'Merchant';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->schema([
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
                        }),
                    TextInput::make('order')
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
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('link_to'),
                TextColumn::make('status')
                    ->formatStateUsing(fn ($state) => MerchantBanner::STATUS[$state] ?? ''),
                TextColumn::make('order')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
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
            'index' => ListMerchantBanners::route('/'),
            'create' => CreateMerchantBanner::route('/create'),
            'edit' => EditMerchantBanner::route('/{record}/edit'),
        ];
    }
}
