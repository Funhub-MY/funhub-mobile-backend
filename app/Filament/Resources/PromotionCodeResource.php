<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromotionCodeResource\Pages;
use App\Jobs\ExportPromotionCodesJob;
use App\Models\PromotionCode;
use App\Models\PromotionCodeGroup;
use App\Models\Reward;
use App\Models\RewardComponent;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\DeleteBulkAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;
use App\Exports\PromotionCodesExport;

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
                    ->url(fn ($record): string => route('filament.resources.promotion-code-groups.edit', $record->promotionCodeGroup ?? ''))
                    ->searchable(),

				Tables\Columns\TextColumn::make('reward_name')
					->label('Reward')
					->getStateUsing(function ($record) {
						if ($record->reward->first()) {
							return $record->reward->first()->name;
						}

						$rewardComponentName = $record->rewardComponent->first()?->name;
						if (!empty($rewardComponentName)) {
							return $rewardComponentName;
						}

						if ($record->promotionCodeGroup && $record->promotionCodeGroup->discount_type === 'fix_amount') {
							return 'Fixed Discount Amount';
						}

						return null;
					}),

				Tables\Columns\TextColumn::make('reward_quantity')
					->label('Value')
					->getStateUsing(function ($record) {
						if ($record->promotionCodeGroup && $record->promotionCodeGroup->discount_type === 'fix_amount') {
							return $record->promotionCodeGroup->discount_amount;
						}

						if ($record->reward->first()) {
							return $record->reward->first()->pivot->quantity;
						}

						return $record->rewardComponent->first()?->pivot?->quantity;
					})
					->sortable(),

				Tables\Columns\TextColumn::make('per_user_limit')
					->label('Per User Limit')
					->getStateUsing(function ($record) {
						if ($record->promotionCodeGroup) {
							$perUserLimit = $record->promotionCodeGroup->per_user_limit;
							return PromotionCodeGroup::PER_USER_LIMIT[$perUserLimit] ?? $perUserLimit;
						}

						return null;
					})
					->sortable(),

				Tables\Columns\TextColumn::make('min_spend_amount')
					->label('Min Spend Amount')
					->getStateUsing(function ($record) {
						if ($record->promotionCodeGroup && $record->promotionCodeGroup->discount_type === 'fix_amount') {
							$minSpend = $record->promotionCodeGroup->min_spend_amount;
							return $minSpend !== null ? number_format($minSpend, 2) : '';
						}

						return null;
					})
					->sortable(),

				Tables\Columns\TextColumn::make('code_quantity')
					->label('Code Quantity')
					->sortable(),

				Tables\Columns\TextColumn::make('used_code_count')
					->label('Used Code Count')
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

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Active')
                    ->enum([
                        false => 'Inactive',
                        true => 'Active',
                    ])
                    ->colors([
                        'danger' => false,
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
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
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
                Tables\Actions\Action::make('toggleStatus')
                    ->label('Toggle Status')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (PromotionCode $record) {
                        $record->update([
                            'status' => !$record->status,
                        ]);
                        
                        Notification::make()
                            ->title($record->status ? 'Code activated' : 'Code deactivated')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Toggle Promotion Code Status')
                    ->modalSubheading('Are you sure you want to change the status of this promotion code?')
                    ->modalButton('Yes, toggle status'),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->requiresConfirmation(),
                Tables\Actions\BulkAction::make('updateStatus')
                    ->label('Update Status')
                    ->icon('heroicon-o-check-circle')
                    ->form([
                        Forms\Components\Toggle::make('status')
                            ->label('Active')
                            ->default(true)
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $records->each(fn ($record) => $record->update([
                            'status' => $data['status'],
                        ]));

                        Notification::make()
                            ->title('Status updated successfully')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion(),
				BulkAction::make('export')
					->label('Export Selected Codes')
					->icon('heroicon-o-download')
					->action(function ($records) {
						$promotionCodeIds = $records->pluck('id')->toArray();

						ExportPromotionCodesJob::dispatch(null, $promotionCodeIds, auth()->id());

						Notification::make()
							->title('Export Started')
							->success()
							->body('Your selected promotion codes are being exported. You will receive a notification when it is ready.')
							->send();
					}),
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
