<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use App\Models\Transaction;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Filament\Resources\UserResource\Pages\EditUser;
use Filament\Tables\Filters\SelectFilter;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use Illuminate\Support\Facades\Log;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Sales';

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
               Group::make()->schema([
                    Section::make('Transaction')
                        ->schema([
                            TextInput::make('transaction_no')
                                ->required(),
                            Select::make('status')
                                ->options(Transaction::STATUS)
                                ->required(),
                            TextInput::make('amount')
                                ->numeric()
                                ->required(),
                            Select::make('gateway')
                                ->options([
                                    'MPAY' => 'MPAY',
                                ])
                                ->required(),
                            TextInput::make('gateway_transaction_id'),
                            TextInput::make('payment_method'),
                            TextInput::make('bank'),
                            TextInput::make('card_last_four')
                                ->numeric(),
                            TextInput::make('card_type'),
                        ])
               ])->columnSpan(['lg' => 2]),

               Group::make()->schema([
                    Section::make('Related')
                        ->schema([
                            Select::make('user_id')
                                ->relationship('user', 'name')
                                ->searchable()
                                ->required(),

                            Select::make('product_id')
                                ->relationship('product', 'name')
                                ->searchable()
                                ->required(),
                        ])
               ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('created_at')
                    ->label('Date Time')
                    ->date('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('transaction_no')
                    ->sortable()
                    ->searchable()
                    ->label('Transaction No'),
                Tables\Columns\BadgeColumn::make('status')
                    ->enum(Transaction::STATUS)
                    ->colors([
                        'secondary' => 0,
                        'success' => 1,
                        'danger' => 2,
                    ])
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user.name')
                    ->sortable()
                    ->searchable()
                    ->label('User'),
                TextColumn::make('transactionable.name')
                    ->label('Item')
                    ->sortable(),
                TextColumn::make('amount')
                    ->sortable()
                    ->label('Amount (RM)'),
                TextColumn::make('payment_method')
                    ->label('Payment Method'),

            ])
            ->filters([
               SelectFilter::make('transactionable_type')
                ->label('Item Type')
                ->options([
                    'App\Models\MerchantOffer' => 'Merchant Offer',
                    'App\Models\Product' => 'Product',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('refresh_status')
                    ->label('Refresh Status')
                    ->icon('heroicon-o-refresh')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        // Initialize MPAY gateway
                        $mpayService = new \App\Services\Mpay(
                            config('services.mpay.mid'),
                            config('services.mpay.hash_key'),
                        );

                        // Skip non-MPAY or success transactions
                        if ($record->gateway !== 'mpay' || $record->status == Transaction::STATUS_SUCCESS) {
                            return false;
                        }

                        try {
                            $response = $mpayService->queryTransaction(
                                $record->transaction_no,
                                $record->amount
                            );

                            if (isset($response['responseCode'])) {
                                $newStatus = match($response['responseCode']) {
                                    '0' => Transaction::STATUS_SUCCESS,
                                    'PE' => Transaction::STATUS_PENDING,
                                    default => Transaction::STATUS_FAILED
                                };

                                $oldStatus = $record->status;
                                if ($oldStatus !== $newStatus) {
                                    $record->status = $newStatus;
                                    $record->save();

                                    // handle merchant offer transactions when status changes to success
                                    if ($newStatus === Transaction::STATUS_SUCCESS && 
                                        ($oldStatus === Transaction::STATUS_PENDING || $oldStatus === Transaction::STATUS_FAILED) &&
                                        $record->transactionable_type === \App\Models\MerchantOffer::class) {
                                        
                                        // find the merchant offer user claim
                                        $merchantOfferClaim = \App\Models\MerchantOfferClaim::where('transaction_no', $record->transaction_no)
                                            ->where('user_id', $record->user_id)
                                            ->first();

                                        if ($merchantOfferClaim) {
                                            // update claim status to success
                                            $merchantOfferClaim->status = \App\Models\MerchantOfferClaim::CLAIM_SUCCESS;
                                            $merchantOfferClaim->save();

                                            // check and update voucher ownership if needed
                                            if ($merchantOfferClaim->voucher_id) {
                                                $voucher = \App\Models\MerchantOfferVoucher::find($merchantOfferClaim->voucher_id);
                                                if ($voucher && (!$voucher->owned_by_id || $voucher->owned_by_id !== $record->user_id)) {
                                                    $voucher->owned_by_id = $record->user_id;
                                                    $voucher->save();
                                                }
                                            }
                                        }
                                    }

                                    // show summary notification
                                    Notification::make()
                                        ->title('Refresh Status Completed')
                                        ->body("Updated Transaction: {$record->transaction_no} status to " . ucfirst($newStatus))
                                        ->success()
                                        ->send();
                                }
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Refresh Status Failed')
                                ->body("Failed to update Transaction: {$record->transaction_no} status")
                                ->danger()
                                ->send();
                            Log::error('[MPAY] Refresh status failed for transaction ' . $record->transaction_no . ': ' . $e->getMessage(), [
                                'error' => $e->getMessage(),
                                'transaction_no' => $record->transaction_no,
                            ]);
                        }                       
                    })
                    ->visible(fn ($record): bool =>
                        $record->gateway === 'mpay' &&
                        $record->status !== Transaction::STATUS_SUCCESS
                    )
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                ExportBulkAction::make()->exports([
                    ExcelExport::make('table')->fromTable(),
                ]),
                
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}
