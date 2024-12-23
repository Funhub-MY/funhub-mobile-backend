<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromotionCodeResource\Pages;
use App\Models\PromotionCode;
use App\Models\PromotionCodeGroup;
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
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Model;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;

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
                Tables\Filters\SelectFilter::make('promotion_code_group')
                    ->label('Promotion Group')
                    ->relationship('promotionCodeGroup', 'name')
                    ->searchable()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('is_redeemed')
                    ->options([
                        '1' => 'Redeemed',
                        '0' => 'Available',
                    ]),
                Tables\Filters\Filter::make('tags')
                    ->form([
                        Forms\Components\TagsInput::make('tags')
                            ->separator(',')
                            ->suggestions([
                                'promotion',
                                'event',
                                'seasonal',
                                'special',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['tags'],
                                fn (Builder $query, $tags): Builder => $query->whereJsonContains('tags', $tags),
                            );
                    })
            ])
            ->actions([
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->requiresConfirmation(),
                ExportBulkAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->label('Export Promotion Codes')
                            ->withColumns([
                                Column::make('code')
                                    ->heading('Code'),
                                Column::make('promotionCodeGroup.name')
                                    ->heading('Group Name'),
                                Column::make('reward_name')
                                    ->heading('Reward')
                                    ->getStateUsing(function ($record) {
                                        if ($record->reward->first()) {
                                            return $record->reward->first()->name;
                                        }
                                        return $record->rewardComponent->first()?->name;
                                    }),
                                Column::make('reward_quantity')
                                    ->heading('Quantity')
                                    ->getStateUsing(function ($record) {
                                        if ($record->reward->first()) {
                                            return $record->reward->first()->pivot->quantity;
                                        }
                                        return $record->rewardComponent->first()?->pivot?->quantity;
                                    }),
                                Column::make('is_redeemed')
                                    ->heading('Status')
                                    ->getStateUsing(fn ($record) => $record->is_redeemed ? 'Redeemed' : 'Not Redeemed'),
                                Column::make('claimedBy.name')
                                    ->heading('Claimed By'),
                                Column::make('redeemed_at')
                                    ->heading('Redeemed At')
                                    ->formatStateUsing(fn ($state) => $state ? $state->format('Y-m-d H:i:s') : ''),
                                Column::make('tags')
                                    ->heading('Tags')
                                    ->getStateUsing(fn ($record) => implode(', ', $record->tags ?? [])),
                            ])
                            ->withFilename(fn() => 'promotion-codes-' . date('Y-m-d'))
                            ->withWriterType(\Maatwebsite\Excel\Excel::CSV)
                    ]),
            ]);
    }

    protected static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_redeemed', false)->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotionCodes::route('/'),
        ];
    }
}
