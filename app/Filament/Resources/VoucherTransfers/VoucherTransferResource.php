<?php

namespace App\Filament\Resources\VoucherTransfers;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use App\Filament\Resources\VoucherTransfers\Pages\ListVoucherTransfers;
use App\Filament\Resources\VoucherTransfers\Pages\CreateVoucherTransfer;
use App\Filament\Resources\VoucherTransfers\Pages\EditVoucherTransfer;
use App\Filament\Resources\VoucherTransferResource\Pages;
use App\Filament\Resources\VoucherTransferResource\RelationManagers;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferClaim;
use App\Models\VoucherTransfer;
use Closure;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;


class VoucherTransferResource extends Resource
{
    protected static ?string $model = VoucherTransfer::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $modelLabel = 'Voucher Transfer';

    protected static string | \UnitEnum | null $navigationGroup = 'Merchant Offers';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 0)->count();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $query->orderBy('status', 'asc')
            ->orderBy('created_at', 'desc');

        return $query;
    }


    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
               Section::make([
                Select::make('merchant_offer_id')
                    ->relationship('merchantOffer', 'name')
                    ->reactive()
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name . ' (' . $record->sku . ')')
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $merchantOffer = MerchantOffer::find($state);
                            $voucherCount = $merchantOffer->unclaimedVouchers()->count();
                            $set('quantity', $voucherCount);
                        } else {
                            $set('quantity', 0);
                        }
                    })
                    ->required(),

                // show quantity field when merchant_offer_id is selected and get latest balance vouchers from merchant offer
                TextInput::make('quantity')
                    ->label('Quantity of Available Vouchers To Transfer')
                    ->reactive()
                    ->hidden(fn (Get $get) => !$get('merchant_offer_id'))
                    ->default(0),

                Hidden::make('from_user_id')
                    ->default(auth()->user()->id),

                Select::make('to_user_id')
                    ->relationship('toUser', 'name')
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->username . ' (ID: ' . $record->id . ')')
                    ->required(),

                Textarea::make('remarks')
                    ->maxLength(255),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('merchantOffer.name')
                    ->label('Merchant Offer'),

                TextColumn::make('merchantOffer.sku')
                    ->label('SKU'),

                TextColumn::make('toUser.name')
                    ->label('To User'),

                TextColumn::make('quantity')
                    ->label('Quantity'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        0 => 'Pending',
                        1 => 'Approved',
                        2 => 'Rejected',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        0 => 'secondary',
                        1 => 'success',
                        2 => 'danger',
                        default => 'gray',
                    }),
                // Tables\Columns\TextColumn::make('remarks'),
                TextColumn::make('transferred_on')
                    ->dateTime(),
                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (VoucherTransfer $record) => $record->status === 0)
                    ->action(function (VoucherTransfer $record) {
                        // if rejected status then can no longer approve, send notification
                        if ($record->status !== 0) {
                            Notification::make()
                                ->title('Voucher Transfer Status Changed failed')
                                ->message('Selected voucher transfer request has been approved or rejected before.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $offer = $record->merchantOffer;

                        $unclaimedVouchers = $record->merchantOffer->unclaimedVouchers()->get();
                        // get latest available vouchers
                        for($i = 0; $i < $record->quantity; $i++) {
                            $voucher = $offer->unclaimedVouchers()->orderBy('id', 'asc')->first();
                            if (!$voucher) {
                                Notification::make()
                                    ->title('Voucher Transfer Failed')
                                    ->message('No more available vouchers to claim.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            // create claim first
                            $offer->claims()->attach($record->to_user_id, [
                                // order no is CLAIM(YMd)
                                'order_no' => 'T'.now()->format('Ymd').$record->to_user_id,
                                'user_id' => $record->to_user_id,
                                'quantity' => 1, // one by one
                                'unit_price' => $offer->unit_price,
                                'total' => $offer->unit_price, // since one only
                                'purchase_method' => 'manual_transfer',
                                'discount' => 0,
                                'tax' => 0,
                                'net_amount' => $offer->unit_price, // since one by one
                                'voucher_id' => $voucher->id,
                                'status' => MerchantOffer::CLAIM_SUCCESS // status set as 1 as right now the offer should be ready to claim.
                            ]);

                            $voucher = $unclaimedVouchers->first();
                            $voucher->update([
                                'owned_by_id' => $record->to_user_id,
                            ]);
                            $unclaimedVouchers->shift(); // so wont repeated same voucher

                            Log::info('[VoucherTransferResource] Voucher Transfer Approved', [
                                'merchant_iffer_id' => $record->merchant_offer_id,
                                'voucher_id' => $voucher->id,
                                'from_user_id' => $record->from_user_id,
                                'to_user_id' => $record->to_user_id,
                            ]);
                        } // end of for loop

                        // change to success status after all done
                        $record->update([
                            'status' => 1,
                            'transferred_on' => now(),
                        ]);
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (VoucherTransfer $record) => $record->status === 0)
                    ->action(function (VoucherTransfer $record) {

                        // only can reject if status is pending
                        if ($record->status !== 0) {
                            Notification::make()
                                ->title('Voucher Transfer Status Changed failed')
                                ->message('Selected voucher transfer request has been approved or rejected before.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $record->update([
                            'status' => 2,
                        ]);
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('approve')
                    ->label('Approve Selected')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (Collection $records) {
                        foreach ($records as $record) {
                            if ($record->status === 0) {
                                $record->update([
                                    'status' => 1,
                                    'transferred_on' => now(),
                                ]);

                                $voucher = $record->merchantOfferVoucher;
                                $voucher->update([
                                    'owned_by_id' => $record->to_user_id,
                                ]);

                                $claim = new MerchantOfferClaim();
                                $claim->merchant_offer_id = $voucher->merchant_offer_id;
                                $claim->user_id = $record->to_user_id;
                                $claim->voucher_id = $voucher->id;
                                $claim->save();
                            }
                        }
                    }),
                BulkAction::make('reject')
                    ->label('Reject Selected')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->action(function (Collection $records) {
                        foreach ($records as $record) {
                            if ($record->status === 0) {
                                $record->update([
                                    'status' => 2,
                                ]);
                            }
                        }
                    }),
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
            'index' => ListVoucherTransfers::route('/'),
            'create' => CreateVoucherTransfer::route('/create'),
            'edit' => EditVoucherTransfer::route('/{record}/edit'),
        ];
    }
}
