<?php

namespace App\Filament\Resources\MerchantOfferWhitelists\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;

class MerchantOfferWhitelistsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('merchantOffer.name')
                    ->label('Offer Name')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                
                TextColumn::make('merchantOffer.user.name')
                    ->label('Merchant User')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('override_days')
                    ->label('Days Limit')
                    ->formatStateUsing(function ($state) {
                        if ($state === null) {
                            return 'Fully Whitelisted (No Restriction)';
                        }
                        return $state . ' days';
                    })
                    ->badge()
                    ->color(fn ($state) => $state === null ? 'success' : 'warning')
                    ->sortable(),
                
                TextColumn::make('merchantOffer.campaign.name')
                    ->label('Campaign')
                    ->searchable()
                    ->limit(30),
                
                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->notes),
                
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('override_days')
                    ->label('Whitelist Type')
                    ->options([
                        'null' => 'Fully Whitelisted (No Restriction)',
                        'not_null' => 'Custom Days Limit',
                    ])
                    ->query(function ($query, $state) {
                        if ($state['value'] === 'null') {
                            return $query->whereNull('override_days');
                        } elseif ($state['value'] === 'not_null') {
                            return $query->whereNotNull('override_days');
                        }
                        return $query;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
