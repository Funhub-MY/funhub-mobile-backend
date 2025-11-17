<?php

namespace App\Filament\Resources\Transactions;


use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use App\Services\Mpay;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferClaim;
use App\Models\MerchantOfferVoucher;
use Exception;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Models\Product;
use App\Notifications\PurchasedGiftCardNotification;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use App\Models\Transaction;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Filament\Resources\Users\Pages\EditUser;
use Filament\Tables\Filters\SelectFilter;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use Illuminate\Support\Facades\Log;
class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Sales';

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
                TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn (int $state): string => Transaction::STATUS[$state] ?? $state)
                ->color(fn (int $state): string => match($state) {
                    0 => 'secondary',
                    1 => 'success',
                    2 => 'danger',
                    default => 'gray',
                })
                ->sortable(),
                TextColumn::make('user.name')
                    ->sortable()
                    ->searchable()
                    //->url(fn ($record) => route('filament.resources.users.view', $record->user))
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

                // status filter
                SelectFilter::make('status')
                    ->options(Transaction::STATUS),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('refresh_status')
                    ->label('Refresh Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        // Initialize MPAY gateway
                        $mpayService = new Mpay(
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

                            // if status is failure(not pending and not success)
                            if (isset($response['responseCode']) 
                                && $response['responseCode'] !== 'PE' 
                                && $response['responseCode'] !== '0') {
                                // failure
                                $newStatus = Transaction::STATUS_FAILED;
                                $oldStatus = $record->status;

                                if ($oldStatus !== $newStatus) {
                                    $record->status = $newStatus;
                                    $record->save();

                                    Log::info('[TransactionResource] Transaction Updated to Failed', [
                                        'transaction_id' => $record->id,
                                        'old_status' => $oldStatus,
                                        'new_status' => $newStatus,
                                        'response' => $response
                                    ]);

                                    Notification::make()
                                        ->title('Transaction '. $record->transaction_no .' Updated to Failed')
                                        ->body('Transaction ' . $record->transaction_no . ' latest status is ' . $newStatus)
                                        ->success()
                                        ->send();
                                }
                            }

                            if (isset($response['responseCode']) && $response['responseCode'] === '0') { // success now
                                $newStatus = Transaction::STATUS_SUCCESS;
                                $oldStatus = $record->status;

                                if ($oldStatus !== $newStatus) {
                                    $record->status = $newStatus;
                                    $record->save();

                                    // handle merchant offer transactions when status changes to success
                                    if ($newStatus === Transaction::STATUS_SUCCESS && 
                                        ($oldStatus === Transaction::STATUS_PENDING || $oldStatus === Transaction::STATUS_FAILED) &&
                                        $record->transactionable_type === MerchantOffer::class) {
                                        
                                        // find the merchant offer user claim
                                        $merchantOfferClaim = MerchantOfferClaim::where('transaction_no', $record->transaction_no)
                                            ->where('user_id', $record->user_id)
                                            ->first();

                                        if ($merchantOfferClaim) {
                                            // update claim status to success
                                            $oldOfferClaimStatus = $merchantOfferClaim->status;
                                            $merchantOfferClaim->status = MerchantOfferClaim::CLAIM_SUCCESS;
                                            $merchantOfferClaim->save();

                                            // check and update voucher ownership if needed
                                            if ($merchantOfferClaim->voucher_id) {
                                                $voucher = MerchantOfferVoucher::find($merchantOfferClaim->voucher_id);
                                                if ($voucher && empty($voucher->owned_by_id)) {
                                                    $voucher->owned_by_id = $record->user_id;
                                                    $voucher->save();
                                                } else {
                                                    Log::error('[TransactionResource] Failed to update voucher ownership as its owned by someone else', [
                                                        'voucher_id' => $merchantOfferClaim->voucher_id,
                                                        'user_id' => $record->user_id
                                                    ]);

                                                    // revert back to old status
                                                    $record->status = $oldStatus;
                                                    $record->save();

                                                    // revert offer claim status
                                                    $merchantOfferClaim->status = $oldOfferClaimStatus;
                                                    $merchantOfferClaim->save();

                                                    Notification::make()
                                                        ->title('Refresh Status Failed')
                                                        ->body("Voucher Code:" . $merchantOfferClaim->voucher->code . "Failed to update voucher ownership as its owned by someone else")
                                                        ->danger()
                                                        ->send();
                                                }
                                            }
                                        }
                                    } else if ($newStatus === Transaction::STATUS_SUCCESS && ($oldStatus === Transaction::STATUS_PENDING || $oldStatus === Transaction::STATUS_FAILED) &&
                                        $record->transactionable_type === Product::class) {

                                        // callback already credited
                                        Log::info('[TransactionResource] Product Refresh, callback to credit triggered', [
                                            'old_status' => $oldStatus,
                                            'new_status' => $newStatus,
                                            'transaction' => $record
                                        ]);

                                        // $product = Product::where('id', $record->transactionable_id)->first();

                                        // if (!$product) {
                                        //     Notification::make()
                                        //         ->title('Refresh Status Failed - Product Not Found')
                                        //         ->body("Updated Transaction: {$record->transaction_no} status to " . ucfirst($newStatus) . " failed as product not found")
                                        //         ->danger()
                                        //         ->send();

                                        //     // revert back to old status
                                        //     $record->status = $oldStatus;
                                        //     $record->save();

                                        //     return false;
                                        // }
                                        // $pointService = new \App\Services\PointService();
                                        // $reward = $product->rewards()->first();
                                        // // credit user
                                        // $pointService->credit(
                                        //     $reward,
                                        //     $record->user,
                                        //     $reward->pivot->quantity,
                                        //     'Gift Card Purchase',
                                        //     $record->transaction_no
                                        // );

                                        // Log::info('[TransactionResource] Prdocut Credited - Updated Transaction: ' . $record->transaction_no . ' status to ' . ucfirst($newStatus) . ' successfully', [
                                        //     'user' => $record->user,
                                        //     'product' => $product,
                                        //     'amount' => $record->amount
                                        // ]);

                                        // update product status
                                        // if ($record->user->email) {
                                        //     try {
                                        //         $product = Product::where('id', $record->transactionable_id)->first();
                                        //         // $quantity = $transaction->amount / $product->unit_price;
                                        //         //  The payment is based on the discount price, so the quantity shall deduct by discount price and not original price
                                        //         $quantity = $record->amount / $product->discount_price;

                                        //         $record->user->notify(new PurchasedGiftCardNotification($record->transaction_no, $record->updated_at, $product->name, $quantity, $record->amount));

                                        //         // fire event for mission progress
                                        //         //event(args: new GiftCardPurchased($record->user, $product));
                                        //     } catch (\Exception $e) {
                                        //         Log::error('Error sending PurchasedGiftCardNotification: ' . $e->getMessage());
                                        //     }
                                        // }
                                    }

                                    // show summary notification
                                    Notification::make()
                                        ->title('Refresh Status Completed')
                                        ->body("Updated Transaction: {$record->transaction_no} status to " . ucfirst($newStatus))
                                        ->success()
                                        ->send();
                                }
                            } else if (isset($response['responseCode']) && $response['responseCode'] == 'M0009') {
                                // transaction not found
                                Notification::make()
                                    ->title('Transaction Not Found')
                                    ->body("Failed to update Transaction: {$record->transaction_no} status as transaction not found")
                                    ->danger()
                                    ->send();
                            } else {
                                $responseDesc = isset($response['responseDesc']) ? $response['responseDesc'] : '';
                                // other errors
                                Notification::make()
                                    ->title('Refresh Status Failed')
                                    ->body("Failed to update Transaction: {$record->transaction_no} status from MPAY: " . $responseDesc)
                                    ->danger()
                                    ->send();
                            }
                        } catch (Exception $e) {
                            Notification::make()
                                ->title('Refresh Status Failed')
                                ->body("Failed to update Transaction: {$record->transaction_no} status. Error: ". $e->getMessage())
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
            ->toolbarActions([
                DeleteBulkAction::make(),
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
            'index' => ListTransactions::route('/'),
            'create' => CreateTransaction::route('/create'),
            'edit' => EditTransaction::route('/{record}/edit'),
        ];
    }
}
