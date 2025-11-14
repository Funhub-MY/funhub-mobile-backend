<?php

namespace App\Filament\Resources\MerchantOfferResource\RelationManagers;

use App\Models\MerchantOffer;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Actions\AttachAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Actions\DetachBulkAction;
use Livewire\Livewire;

class LocationRelationManager extends RelationManager
{
    protected static string $relationship = 'location';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->url(fn ($record) => route('filament.resources.locations.edit', $record->id))
                    ->description(fn ($record) => $record->full_address),
                Tables\Columns\TextColumn::make('state.name'),
                Tables\Columns\TextColumn::make('country.name'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        // TextInput::make('rating')
                        //     ->numeric()
                        //     ->nullable()
                        //     ->rules('min:1', 'max:5')
                        //     ->label('Rating')
                    ])
                    ->after(function (AttachAction $action, RelationManager $livewire) {
                        // get the current merchant offer
                        $merchantOffer = $livewire->ownerRecord;
                        $locationId = $action->getFormData()['recordId'];
                        // $locationRating = $action->getFormData()['rating'] ?? null;

                        // check if it belongs to a campaign
                        if ($merchantOffer->merchant_offer_campaign_id) {
                            try {
                                // Get all other offers from the same campaign
                                $campaign = $merchantOffer->campaign;
                                $otherOffers = $campaign->merchantOffers()
                                    ->where('id', '!=', $merchantOffer->id)
                                    ->get();

                                // sync the location to all other offers
                                foreach ($otherOffers as $offer) {
                                    $offer->location()->attach($locationId);
                                }

                                Notification::make()
                                    ->success()
                                    ->title('Location synced to all campaign offers')
                                    ->body("Location has been attached to {$otherOffers->count()} other offers in this campaign.")
                                    ->send();

                            } catch (\Exception $e) {
                                Log::error('Failed to sync location to campaign offers', [
                                    'error' => $e->getMessage(),
                                    'merchant_offer_id' => $merchantOffer->id,
                                    'campaign_id' => $merchantOffer->merchant_offer_campaign_id,
                                    'location_id' => $locationId
                                ]);

                                Notification::make()
                                    ->danger()
                                    ->title('Failed to sync locations')
                                    ->body('Location was attached to this offer but failed to sync with other campaign offers.')
                                    ->send();
                            }
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make()
                    ->after(function (DetachAction $action, RelationManager $livewire) {
                    // get the current merchant offer
                        $merchantOffer = $livewire->ownerRecord;
                        $locationId = $action->getRecord()->id;

                        // check if it belongs to a campaign
                        if ($merchantOffer->merchant_offer_campaign_id) {
                            try {
                                // get all other offers from the same campaign
                                $campaign = $merchantOffer->campaign;
                                $otherOffers = $campaign->merchantOffers()
                                    ->where('id', '!=', $merchantOffer->id)
                                    ->get();

                                // detach the location from all other offers
                                foreach ($otherOffers as $offer) {
                                    $offer->location()->detach($locationId);
                                }

                                Notification::make()
                                    ->success()
                                    ->title('Location removed from all campaign offers')
                                    ->send();

                            } catch (\Exception $e) {
                                Log::error('Failed to remove location from campaign offers', [
                                    'error' => $e->getMessage(),
                                    'merchant_offer_id' => $merchantOffer->id,
                                    'campaign_id' => $merchantOffer->merchant_offer_campaign_id,
                                    'location_id' => $locationId
                                ]);

                                Notification::make()
                                    ->danger()
                                    ->title('Failed to sync location removal')
                                    ->body('Location was detached from this offer but failed to remove from other campaign offers.')
                                    ->send();
                            }
                        }
                    }),
                ]);
    }
}
