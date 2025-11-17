<?php

namespace App\Filament\Resources\PromotionCodeGroups;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use App\Filament\Resources\PromotionCodeGroups\Pages\EditPromotionCodeGroup;
use Closure;
use App\Models\PromotionCode;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use App\Filament\Resources\PromotionCodeGroups\Pages\CreatePromotionCodeGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use App\Filament\Resources\PromotionCodeGroups\Pages\ListPromotionCodeGroups;
use App\Filament\Resources\PromotionCodeGroupResource\Pages;
use App\Filament\Resources\PromotionCodeGroupResource\RelationManagers;
use App\Jobs\ExportPromotionCodesJob;
use App\Models\PromotionCodeGroup;
use App\Models\Reward;
use App\Models\RewardComponent;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;
use App\Exports\PromotionCodesExport;

class PromotionCodeGroupResource extends Resource
{
    protected static ?string $model = PromotionCodeGroup::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-ticket';
    protected static string | \UnitEnum | null $navigationGroup = 'Points & Rewards';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Section::make()
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Textarea::make('description')
                                    ->maxLength(65535),

                                Toggle::make('status')
                                    ->default(false)
                                    ->required(),
                                Grid::make(2)
                                    ->schema([
                                        DateTimePicker::make('campaign_from')
                                            ->minDate(now()->startOfDay())
                                            ->required(),
                                        DateTimePicker::make('campaign_until')
                                            ->required(),
                                    ]),
                            ])
                            ->columnSpan(1),

                        Section::make()
                            ->schema([
                                Select::make('code_type')
									->label('Code Type')
                                    ->options(PromotionCodeGroup::CODE_TYPES)
//                                    ->disabled(fn ($livewire) => $livewire instanceof Pages\EditPromotionCodeGroup)
                                    ->reactive()
                                    ->required(),

								TextInput::make('static_code')
									->label('Code')->helperText('Custom Code')
									->minLength(8)
									->maxLength(12)
									->disabled(fn ($livewire) => $livewire instanceof EditPromotionCodeGroup)
									->visible(fn(callable $get) => $get('code_type') === 'static')
									->required(fn(callable $get) => $get('code_type') === 'static')
									->rules([
										fn ($livewire) => function (string $attribute, $value, Closure $fail) use ($livewire) {
											if (!$value) return; // Skip if empty

											$query = PromotionCode::where('code', $value);

											// If we're editing, ignore the current promotion code group's codes
											if ($livewire instanceof EditPromotionCodeGroup && $livewire->record) {
												$query->where('promotion_code_group_id', '!=', $livewire->record->id);
											}

											if ($query->exists()) {
												$fail('The code has already been taken.');
											}
										},
									]),

                                TextInput::make('total_codes')
                                    ->label('Number of Codes to Generate')
                                    ->visible(fn(callable $get) => $get('code_type'))
                                    ->required(fn(callable $get) => $get('code_type'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->disabled(fn ($livewire) => $livewire instanceof EditPromotionCodeGroup)
                                    ->default(1),

                                Select::make('discount_type')
                                    ->label('Discount Type')
                                    ->options(PromotionCodeGroup::DISCOUNT_TYPES)
//                                    ->disabled(fn ($livewire) => $livewire instanceof Pages\EditPromotionCodeGroup)
                                    ->visible(fn(callable $get) => $get('code_type'))
                                    ->required(fn(callable $get) => $get('code_type'))
                                    ->reactive(),

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
                                    ->visible(fn (callable $get) => $get('discount_type') === 'reward')
                                    ->required(fn ($livewire) => $livewire instanceof CreatePromotionCodeGroup)
                                    ->disabled(fn ($livewire) => $livewire instanceof EditPromotionCodeGroup),

                                /*
								Forms\Components\Toggle::make('use_fix_amount_discount')
									->label('Use Fix Amount Discount')
									->required()
									->default(false)
									->reactive()
                                    ->visible(fn(callable $get) => $get('discount_type') === 'fix_amount_discount')
									->disabled(fn ($livewire) => $livewire instanceof Pages\EditPromotionCodeGroup),
                                */

								TextInput::make('discount_amount')
									->label('Discount Amount')
									->numeric()
									->minValue(0)
									->visible(fn (callable $get) => $get('discount_type') === 'fix_amount')
									->required(fn (callable $get) => $get('discount_type') === 'fix_amount')
									->disabled(fn ($livewire) => $livewire instanceof EditPromotionCodeGroup),

								Select::make('user_type')
									->label('User Type')
									->options(PromotionCodeGroup::USER_TYPES)
									//->default(array_key_first(PromotionCodeGroup::USER_TYPES)) // 'all'
									->required(fn(callable $get) => $get('discount_type'))
                                    ->visible(fn(callable $get) => $get('discount_type'))
									->disabled(fn ($livewire) => $livewire instanceof EditPromotionCodeGroup),

                                TextInput::make('quantity')
                                    ->label('Reward Quantity')
                                    ->helperText('How many rewards to give when code is redeemed')
									->visible(fn (callable $get) => $get('discount_type') === 'reward')
									->numeric()
                                    ->default(1)
                                    ->required(fn ($livewire) => $livewire instanceof CreatePromotionCodeGroup)
                                    ->disabled(fn ($livewire) => $livewire instanceof EditPromotionCodeGroup),

								TextInput::make('min_spend_amount')
									->label('Min Spend Amount (RM)')
									->helperText('Minimum spend amount required to redeem this code')
									->numeric()
									->visible(fn(callable $get) => $get('discount_type') === 'fix_amount')
									->required(fn(callable $get) => $get('discount_type') === 'fix_amount')
									->default(0)
									->disabled(fn ($livewire) => $livewire instanceof EditPromotionCodeGroup)
									->rule(function (callable $get) {
										return function (string $attribute, $value, $fail) use ($get) {
											$discountAmount = $get('discount_amount');
											if ($value <= $discountAmount) {
												$fail('Min spend must be larger than the discount amount.');
											}
										};
									}),

                                Select::make('per_user_limit')
                                    ->label('Per User Limit')
                                    ->helperText('Maximum number of times a user can redeem this code')
                                    ->disabled(fn ($livewire) => $livewire instanceof EditPromotionCodeGroup)
                                    ->visible(fn(callable $get) => $get('discount_type'))
                                    ->required(fn(callable $get) => $get('discount_type'))
									->options(PromotionCodeGroup::PER_USER_LIMIT)
                                    ->reactive(),
                                
                                TextInput::make('per_user_limit_count')
                                    ->label('Limit Count')
                                    ->helperText('Number of times a user can redeem this code')
                                    ->numeric()
                                    ->default(1)
                                    ->disabled(fn ($livewire) => $livewire instanceof EditPromotionCodeGroup)
                                    ->visible(fn(callable $get) => $get('per_user_limit') == 1)
                                    ->required(fn(callable $get) => $get('per_user_limit') == 1),

                                 Select::make('products')
                                     ->relationship('products', 'name')
                                     ->multiple()
                                     ->preload()
                                     ->label('Specific Products (Leave empty to apply to all products)')
									 ->getOptionLabelFromRecordUsing(fn ($record) => "ID:{$record->id} - {$record->name}")
									 ->visible(fn (callable $get) => $get('discount_type') === 'fix_amount')
                                     ->disabled(fn ($livewire) => $livewire instanceof EditPromotionCodeGroup),
								
                                Select::make('paymentMethods')
                                    ->relationship('paymentMethods', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->label('Payment Methods (Leave empty to apply to all payment methods)')
                                    ->visible(fn (callable $get) => $get('discount_type') === 'fix_amount')
                                    ->disabled(fn ($livewire) => $livewire instanceof EditPromotionCodeGroup),
                            ])
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(50),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        false => 'Inactive',
                        true => 'Active',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        false => 'danger',
                        true => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('campaign_from')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('campaign_until')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('status'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('toggleStatus')
                    ->label(fn ($record) => $record->status ? 'Deactivate' : 'Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color(fn ($record) => $record->status ? 'danger' : 'success')
                    ->action(function ($record) {
                        $newStatus = !$record->status;

                        $record->update([
                            'status' => $newStatus,
                        ]);

                        PromotionCode::where('promotion_code_group_id', $record->id)
                            ->update(['status' => $newStatus]);

                        Notification::make()
                            ->title('Status updated successfully')
                            ->body('All promotion codes in this group have been ' . ($newStatus ? 'activated' : 'deactivated'))
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => ($record->status ? 'Deactivate' : 'Activate') . ' promotion code group')
                    ->modalSubheading(fn ($record) => 'Are you sure you want to ' . ($record->status ? 'deactivate' : 'activate') . ' this promotion code group? This will also ' . ($record->status ? 'deactivate' : 'activate') . ' all promotion codes in this group.')
                    ->modalButton(fn ($record) => $record->status ? 'Deactivate' : 'Activate'),
				Action::make('export')
					->label('Export Codes')
					->button()
					->action(function ($record) {
						ExportPromotionCodesJob::dispatch($record, [], auth()->id());

						Notification::make()
							->title('Export Queued')
							->body('The promotion codes are being exported. You will be notified when it’s ready.')
							->success()
							->send();
					}),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
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
                        $records->each(function ($record) use ($data) {
                            $record->update([
                                'status' => $data['status'],
                            ]);
                            PromotionCode::where('promotion_code_group_id', $record->id)
                                ->update(['status' => $data['status']]);
                        });

                        Notification::make()
                            ->title('Status updated successfully')
                            ->body('All promotion codes in the selected groups have been ' . ($data['status'] ? 'activated' : 'deactivated'))
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion(),
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
            'index' => ListPromotionCodeGroups::route('/'),
            'create' => CreatePromotionCodeGroup::route('/create'),
            'edit' => EditPromotionCodeGroup::route('/{record}/edit'),
        ];
    }
}
