<?php

namespace App\Filament\Resources\PromotionCodes;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Toggle;
use App\Filament\Resources\PromotionCodes\Pages\ListPromotionCodes;
use App\Filament\Resources\PromotionCodes\Pages\ViewPromotionCode;
use App\Filament\Resources\PromotionCodeResource\Pages;
use App\Jobs\ExportPromotionCodesJob;
use App\Models\PromotionCode;
use App\Models\PromotionCodeGroup;
use App\Models\Reward;
use App\Models\RewardComponent;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;
use App\Exports\PromotionCodesExport;
use App\Filament\Resources\PromotionCodes\RelationManagers\UsersRelationManager;

class PromotionCodeResource extends Resource
{
    protected static ?string $model = PromotionCode::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-ticket';
    protected static string | \UnitEnum | null $navigationGroup = 'Points & Rewards';

    // disallow edit / create
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('number_of_codes')
                            ->label('Number of Codes to Generate')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1),
                            
                        TagsInput::make('tags')
                            ->separator(',')
                            ->suggestions([
                                'promotion',
                                'event',
                                'seasonal',
                                'special',
                            ]),

                        // reward type using MorphToSelect
                        MorphToSelect::make('rewardable')
                            ->label('Reward Type')
                            ->types([
                                Type::make(Reward::class)
                                    ->titleColumnName('name')
                                    ->modifyOptionsQueryUsing(function ($query) {
                                        return $query->select('rewards.id', 'rewards.name')
                                            ->whereIn('id', function($subquery) {
                                                $subquery->selectRaw('MIN(id)')
                                                    ->from('rewards')
                                                    ->groupBy('name');
                                            });
                                    }),
                                Type::make(RewardComponent::class)
                                    ->titleColumnName('name'),
                            ])
                            ->required(),

                        TextInput::make('quantity')
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
                TextColumn::make('code')
                    ->searchable(),

                TextColumn::make('promotionCodeGroup.name')
                    ->label('Group Name')
                    ->url(fn ($record) => $record->promotionCodeGroup ? route('filament.admin.resources.promotion-code-groups.edit', $record->promotionCodeGroup) : null)
                    ->searchable(),

				TextColumn::make('reward_name')
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

				TextColumn::make('reward_quantity')
					->label('Value')
					->getStateUsing(function ($record) {
						if ($record->promotionCodeGroup && $record->promotionCodeGroup->discount_type === 'fix_amount') {
							return $record->promotionCodeGroup->discount_amount;
						}

						if ($record->reward->first()) {
							return $record->reward->first()->pivot->quantity;
						}

						return $record->rewardComponent->first()?->pivot?->quantity;
					}),

				TextColumn::make('per_user_limit')
					->label('Per User Limit')
					->getStateUsing(function ($record) {
						if ($record->promotionCodeGroup) {
							$perUserLimit = $record->promotionCodeGroup->per_user_limit;
							return PromotionCodeGroup::PER_USER_LIMIT[$perUserLimit] ?? $perUserLimit;
						}

						return null;
					}),

				TextColumn::make('min_spend_amount')
					->label('Min Spend Amount')
					->getStateUsing(function ($record) {
						if ($record->promotionCodeGroup && $record->promotionCodeGroup->discount_type === 'fix_amount') {
							$minSpend = $record->promotionCodeGroup->min_spend_amount;
							return $minSpend !== null ? number_format($minSpend, 2) : '';
						}

						return null;
					}),

				TextColumn::make('code_quantity')
					->label('Total Code Generated')
					->sortable(),

				TextColumn::make('used_code_count')
					->label('Total Code Claimed')
					->sortable(),

                TextColumn::make('is_redeemed')
                    ->label('Redeemed Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        true => 'Redeemed',
                        false => 'Not Redeemed',
                    })
                    ->color(fn ($state) => match($state) {
                        true => 'success',
                        false => 'warning',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        true => 'Active',
                        false => 'Inactive',
                    })
                    ->color(fn ($state) => match($state) {
                        true => 'success',
                        false => 'danger',
                    }),    

                TagsColumn::make('tags')
                    ->separator(','),

                TextColumn::make('claimedBy.name')
                    ->label('Claimed By')
                    ->searchable(),

                TextColumn::make('redeemed_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('promotion_code_group')
                    ->label('Promotion Group')
                    ->relationship('promotionCodeGroup', 'name')
                    ->searchable()
                    ->multiple(),

                SelectFilter::make('is_redeemed')
                    ->options([
                        '1' => 'Redeemed',
                        '0' => 'Available',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
                Filter::make('tags')
                    ->schema([
                        TagsInput::make('tags')
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
            ->recordActions([
                ViewAction::make(),
                Action::make('toggleStatus')
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
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->requiresConfirmation(),
                BulkAction::make('updateStatus')
                    ->label('Update Status')
                    ->icon('heroicon-o-check-circle')
                    ->schema([
                        Toggle::make('status')
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
					->icon('heroicon-o-arrow-down-tray')
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

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_redeemed', false)->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotionCodes::route('/'),
            'view' => ViewPromotionCode::route('/{record}'),
        ];
    }
    
    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }
}
