<?php

namespace App\Filament\Resources\MerchantOfferWhitelists\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use App\Models\MerchantOffer;
use App\Models\User;

class MerchantOfferWhitelistForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Offer Whitelist Details')
                    ->schema([
                        Select::make('merchant_offer_id')
                            ->label('Merchant Offer')
                            ->relationship('merchantOffer', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Auto-populate merchant_user_id when offer is selected
                                if ($state) {
                                    $offer = MerchantOffer::find($state);
                                    if ($offer && $offer->user_id) {
                                        $set('merchant_user_id', $offer->user_id);
                                    }
                                }
                            })
                            ->helperText('Select the merchant offer to whitelist'),
                        
                        Placeholder::make('merchant_user_display')
                            ->label('Merchant User')
                            ->content(function ($get, $record) {
                                $offerId = $get('merchant_offer_id') ?? ($record?->merchant_offer_id);
                                if ($offerId) {
                                    $offer = MerchantOffer::find($offerId);
                                    if ($offer && $offer->user) {
                                        return $offer->user->name . ' (ID: ' . $offer->user_id . ')';
                                    }
                                }
                                return 'Select an offer first';
                            })
                            ->visible(fn ($get, $record) => ($record !== null && $record->merchantOffer) || $get('merchant_offer_id') !== null),
                        
                        TextInput::make('override_days')
                            ->label('Custom Days Limit')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Leave empty to fully whitelist (no restriction). Set a value to use custom days limit instead of config default (' . config('app.same_merchant_spend_limit_days', 30) . ' days).')
                            ->placeholder('e.g., 15, 30, 60')
                            ->suffix('days'),
                        
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->helperText('Optional notes about why this offer is whitelisted')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
