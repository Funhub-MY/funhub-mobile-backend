<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromotionCodeGroupResource\Pages;
use App\Filament\Resources\PromotionCodeGroupResource\RelationManagers;
use App\Jobs\ExportPromotionCodesJob;
use App\Models\PromotionCodeGroup;
use App\Models\Reward;
use App\Models\RewardComponent;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
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

    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Points & Rewards';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Card::make()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('description')
                                    ->maxLength(65535),

                                Forms\Components\Toggle::make('status')
                                    ->default(false)
                                    ->required(),
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\DateTimePicker::make('campaign_from')
                                            ->minDate(now()->startOfDay())
                                            ->required(),
                                        Forms\Components\DateTimePicker::make('campaign_until')
                                            ->required(),
                                    ]),
                            ])
                            ->columnSpan(1),

                        Forms\Components\Card::make()
                            ->schema([
                                Forms\Components\Select::make('code_type')->label('Code Type')
                                    ->options(PromotionCodeGroup::CODE_TYPES)
                                    ->disabled(fn ($livewire) => $livewire instanceof Pages\EditPromotionCodeGroup)
                                    ->reactive()
                                    ->required(),

                                Forms\Components\TextInput::make('total_codes')
                                    ->label('Number of Codes to Generate')
                                    ->visible(fn(callable $get) => $get('code_type') === 'random')
                                    ->required(fn(callable $get) => $get('code_type') === 'random')
                                    ->numeric()
                                    ->minValue(1)
                                    ->disabled(fn ($livewire) => $livewire instanceof Pages\EditPromotionCodeGroup)
                                    ->default(1),

                                Forms\Components\TextInput::make('static_code')
                                    ->label('Code')->helperText('Custom Code')
                                    ->hiddenOn('edit')
                                    ->visible(fn(callable $get) => $get('code_type') === 'static')
                                    ->required(fn(callable $get) => $get('code_type') === 'static'),
                                Forms\Components\Select::make('discount_type')
                                    ->label('Discount Type')
                                    ->options(PromotionCodeGroup::DISCOUNT_TYPES)
                                    ->disabled(fn ($livewire) => $livewire instanceof Pages\EditPromotionCodeGroup)
                                    ->visible(fn(callable $get) => $get('code_type'))
                                    ->required(fn(callable $get) => $get('code_type'))
                                    ->reactive(),

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
                                    ->visible(fn (callable $get) => $get('discount_type') === 'reward')
                                    ->required(fn ($livewire) => $livewire instanceof Pages\CreatePromotionCodeGroup)
                                    ->disabled(fn ($livewire) => $livewire instanceof Pages\EditPromotionCodeGroup),

                                /*
								Forms\Components\Toggle::make('use_fix_amount_discount')
									->label('Use Fix Amount Discount')
									->required()
									->default(false)
									->reactive()
                                    ->visible(fn(callable $get) => $get('discount_type') === 'fix_amount_discount')
									->disabled(fn ($livewire) => $livewire instanceof Pages\EditPromotionCodeGroup),
                                */

								Forms\Components\TextInput::make('discount_amount')
									->label('Discount Amount')
									->numeric()
									->minValue(0)
									->visible(fn (callable $get) => $get('discount_type') === 'fix_amount')
									->required(fn (callable $get) => $get('discount_type') === 'fix_amount')
									->disabled(fn ($livewire) => $livewire instanceof Pages\EditPromotionCodeGroup),
								Forms\Components\Select::make('user_type')
									->label('User Type')
									->options(PromotionCodeGroup::USER_TYPES)
									//->default(array_key_first(PromotionCodeGroup::USER_TYPES)) // 'all'
									->required(fn(callable $get) => $get('discount_type'))
                                    ->visible(fn(callable $get) => $get('discount_type'))
									->disabled(fn ($livewire) => $livewire instanceof Pages\EditPromotionCodeGroup),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Reward Quantity')
                                    ->helperText('How many rewards to give when code is redeemed')
									->visible(fn (callable $get) => $get('discount_type') === 'reward')
									->numeric()
                                    ->default(1)
                                    ->required(fn ($livewire) => $livewire instanceof Pages\CreatePromotionCodeGroup)
                                    ->disabled(fn ($livewire) => $livewire instanceof Pages\EditPromotionCodeGroup),

                                Forms\Components\TextInput::make('min_spend_amount')
                                    ->label('Min Spend Amount (RM)')
                                    ->helperText('Minimum spend amount required to redeem this code')
                                    ->numeric()
                                    ->visible(fn(callable $get) => $get('discount_type'))
                                    ->required(fn(callable $get) => $get('discount_type'))
                                    ->default(0)
                                    ->disabled(fn ($livewire) => $livewire instanceof Pages\EditPromotionCodeGroup),

                                Forms\Components\Select::make('per_user_limit')->label('Per User Limit')
                                    ->label('Per User Limit')
                                    ->helperText('Maximum number of times a user can redeem this code')
                                    ->disabled(fn ($livewire) => $livewire instanceof Pages\EditPromotionCodeGroup)
                                    ->visible(fn(callable $get) => $get('discount_type'))
                                    ->required(fn(callable $get) => $get('discount_type'))
                                    ->options([
                                        0 => 'Unlimited',
                                        1 => 'One Time'
                                    ]),
                                 Forms\Components\Select::make('products')
                                     ->relationship('products', 'name')
                                     ->multiple()
                                     ->preload()
                                     ->label('Specific Products (Leave empty to apply to all products)')
                                     ->visible(fn (callable $get) => $get('discount_type'))
                                     ->disabled(fn ($livewire) => $livewire instanceof Pages\EditPromotionCodeGroup),
                                Forms\Components\Select::make('paymentMethods')
                                    ->relationship('paymentMethods', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->label('Payment Methods (Leave empty to apply to all payment methods)')
                                    ->visible(fn (callable $get) => $get('discount_type') === 'fix_amount')
                                    ->disabled(fn ($livewire) => $livewire instanceof Pages\EditPromotionCodeGroup),
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50),
                Tables\Columns\BadgeColumn::make('status')
                    ->enum([
                        false => 'Inactive',
                        true => 'Active',
                    ])
                    ->colors([
                        'danger' => false,
                        'success' => true,
                    ]),
                Tables\Columns\TextColumn::make('campaign_from')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('campaign_until')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('toggleStatus')
                    ->label(fn ($record) => $record->status ? 'Deactivate' : 'Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color(fn ($record) => $record->status ? 'danger' : 'success')
                    ->action(function ($record) {
                        $newStatus = !$record->status;

                        $record->update([
                            'status' => $newStatus,
                        ]);

                        \App\Models\PromotionCode::where('promotion_code_group_id', $record->id)
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
				Tables\Actions\Action::make('export')
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
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
                        $records->each(function ($record) use ($data) {
                            $record->update([
                                'status' => $data['status'],
                            ]);
                            \App\Models\PromotionCode::where('promotion_code_group_id', $record->id)
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
            'index' => Pages\ListPromotionCodeGroups::route('/'),
            'create' => Pages\CreatePromotionCodeGroup::route('/create'),
            'edit' => Pages\EditPromotionCodeGroup::route('/{record}/edit'),
        ];
    }
}
