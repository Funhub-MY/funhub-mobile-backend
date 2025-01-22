<?php

namespace App\Filament\Resources\ProductCreditResource\Pages;

use App\Filament\Resources\ProductCreditResource;
use App\Models\Product;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Services\TransactionService;
use App\Models\Transaction;
use App\Notifications\PurchasedGiftCardNotification;
use App\Services\PointService;
use App\Services\PointComponentService;
use Illuminate\Support\Facades\Log;

class CreateProductCredit extends CreateRecord
{
    protected static string $resource = ProductCreditResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        
        // create transaction
        $transactionService = app(TransactionService::class);
        $transaction = $transactionService->create(
            $record->product,
            $record->product->unit_price, // since only one record
            'manual',
            $record->user_id,
            'manual',
            'admin',
            null,
            null,
            $record->ref_no
        );

        // mark transaction as successful
        $transaction->status = \App\Models\Transaction::STATUS_SUCCESS;
        $transaction->save();

        // get product reward if any
        $reward = $record->product->rewards()->first();
        if ($reward) {
            $pointService = app(PointService::class);
            
            // credit user with reward points
            $pointService->credit(
                $reward,
                $record->user,
                $reward->pivot->quantity,
                'Manual Product Credit',
                $transaction->transaction_no
            );
        }

        if ($transaction->user->email) {
            try {
                $product = Product::where('id', $transaction->transactionable_id)->first();
                $transaction->user->notify(new PurchasedGiftCardNotification(
                    $transaction->transaction_no, 
                    $transaction->updated_at, 
                    $product->name, 
                    1, 
                    $transaction->amount));
            } catch (\Exception $e) {
                Log::error('Error sending PurchasedGiftCardNotification: ' . $e->getMessage());
            }
        }
    }
}
