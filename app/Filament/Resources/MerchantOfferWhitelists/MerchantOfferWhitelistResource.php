<?php

namespace App\Filament\Resources\MerchantOfferWhitelists;

use App\Filament\Resources\MerchantOfferWhitelists\Pages\CreateMerchantOfferWhitelist;
use App\Filament\Resources\MerchantOfferWhitelists\Pages\EditMerchantOfferWhitelist;
use App\Filament\Resources\MerchantOfferWhitelists\Pages\ListMerchantOfferWhitelists;
use App\Filament\Resources\MerchantOfferWhitelists\Pages\ViewMerchantOfferWhitelist;
use App\Filament\Resources\MerchantOfferWhitelists\Schemas\MerchantOfferWhitelistForm;
use App\Filament\Resources\MerchantOfferWhitelists\Schemas\MerchantOfferWhitelistInfolist;
use App\Filament\Resources\MerchantOfferWhitelists\Tables\MerchantOfferWhitelistsTable;
use App\Models\MerchantOfferWhitelist;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MerchantOfferWhitelistResource extends Resource
{
    protected static ?string $model = MerchantOfferWhitelist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    
    protected static string | \UnitEnum | null $navigationGroup = 'Merchant Offers';
    
    protected static ?int $navigationSort = 2; // After OfferLimitWhitelist (which is likely 1)

    public static function form(Schema $schema): Schema
    {
        return MerchantOfferWhitelistForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MerchantOfferWhitelistInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MerchantOfferWhitelistsTable::configure($table);
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
            'index' => ListMerchantOfferWhitelists::route('/'),
            'create' => CreateMerchantOfferWhitelist::route('/create'),
            'view' => ViewMerchantOfferWhitelist::route('/{record}'),
            'edit' => EditMerchantOfferWhitelist::route('/{record}/edit'),
        ];
    }
}
