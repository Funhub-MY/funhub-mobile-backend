<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromotionCodeResource\Pages;
use App\Models\PromotionCode;
use App\Models\Reward;
use App\Models\RewardComponent;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class PromotionCodeResource extends Resource
{
    protected static ?string $model = PromotionCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Points & Rewards';

    // disallow edit / create
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('number_of_codes')
                            ->label('Number of Codes to Generate')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1),
                            
                        Forms\Components\TagsInput::make('tags')
                            ->separator(',')
                            ->suggestions([
                                'promotion',
                                'event',
                                'seasonal',
                                'special',
                            ]),

                        // reward type using MorphToSelect
                        Forms\Components\MorphToSelect::make('rewardable')
                            ->label('Reward Type')
                            ->types([
                                Forms\Components\MorphToSelect\Type::make(Reward::class)
                                    ->titleColumnName('name')
                                    ->modifyOptionsQueryUsing(function ($query) {
                                        return $query->select('rewards.id', 'rewards.name')
                                            ->whereIn('id', function($subquery) {
                                                $subquery->selectRaw('MIN(id)')
                                                    ->from('rewards')
                                                    ->groupBy('name');
                                            });
                                    }),
                                Forms\Components\MorphToSelect\Type::make(RewardComponent::class)
                                    ->titleColumnName('name'),
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Reward Quantity')
                            ->helperText('How many rewards to give when code is redeemed')
                            ->numeric()
                            ->default(1)
                            ->required(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable(),

                Tables\Columns\TextColumn::make('promotionCodeGroup.name')
                    ->label('Group Name')
                    ->url(fn ($record): string => route('filament.resources.promotion-code-groups.edit', $record->promotionCodeGroup))
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('reward_name')
                    ->label('Reward')
                    ->getStateUsing(function ($record) {
                        if ($record->reward->first()) {
                            return $record->reward->first()->name;
                        }
                        return $record->rewardComponent->first()?->name;
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('reward_quantity')
                    ->label('Quantity')
                    ->getStateUsing(function ($record) {
                        if ($record->reward->first()) {
                            return $record->reward->first()->pivot->quantity;
                        }
                        return $record->rewardComponent->first()?->pivot?->quantity;
                    })
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('is_redeemed')
                    ->label('Status')
                    ->enum([
                        false => 'Not Redeemed',
                        true => 'Redeemed',
                    ])
                    ->colors([
                        'warning' => false,
                        'success' => true,
                    ]),

                Tables\Columns\TagsColumn::make('tags')
                    ->separator(','),

                Tables\Columns\TextColumn::make('claimedBy.name')
                    ->label('Claimed By')
                    ->searchable(),

                Tables\Columns\TextColumn::make('redeemed_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_redeemed')
                    ->options([
                        '1' => 'Redeemed',
                        '0' => 'Available',
                    ]),
                Tables\Filters\Filter::make('tags')
                    ->form([
                        Forms\Components\TagsInput::make('tags')
                            ->separator(','),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['tags'],
                            fn (Builder $query, $tags): Builder => $query->whereJsonContains('tags', $tags)
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\BulkAction::make('updateTags')
                    ->label('Update Tags')
                    ->icon('heroicon-o-tag')
                    ->form([
                        Forms\Components\TagsInput::make('tags')
                            ->label('Tags')
                            ->required()
                            ->suggestions([
                                'promotion',
                                'event',
                                'seasonal',
                                'special',
                            ])
                    ])
                    ->action(function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            $record->update([
                                'tags' => $data['tags'],
                            ]);
                        }

                        Notification::make()
                            ->success()
                            ->title('Tags updated')
                            ->body('The tags have been updated for the selected promotion codes.');
                    })
                    ->deselectRecordsAfterCompletion()
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['reward', 'rewardComponent']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotionCodes::route('/'),
            'edit' => Pages\EditPromotionCode::route('/{record}/edit'),
        ];
    }
}
