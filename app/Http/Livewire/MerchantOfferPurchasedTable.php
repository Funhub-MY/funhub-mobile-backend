<?php

namespace App\Http\Livewire;

use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;
use Livewire\Component;
use App\Models\MerchantOfferClaim;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Relations\Relation;

class MerchantOfferPurchasedTable extends Component implements HasTable, HasActions
{
    use InteractsWithActions;
    use InteractsWithTable;

    public $currentRouteId;

    public function render()
    {
        return view('livewire.merchant-offer-purchased-table');
    }

    public function mount($currentRouteId)
    {
        $this->currentRouteId = $currentRouteId;
    }

    protected function getColumns(): int | string | array
    {
        return 2;
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('id')
                ->label('ID')
                ->sortable()
                ->searchable(),
            TextColumn::make('created_at')
                ->dateTime()
                ->sortable(),
            TextColumn::make('updated_at')
                ->dateTime()
                ->sortable(),
            TextColumn::make('merchantOffer.name')
                ->label('Item Name')
                ->sortable()
                ->searchable(),
            TextColumn::make('voucher.code')
                ->copyable()
                ->searchable(),
            TextColumn::make('net_amount')
                ->label('Net Amount'),
            TextColumn::make('unit_price')
                ->label('Unit Price'),
            TextColumn::make('quantity'),
            TextColumn::make('discount'),
            TextColumn::make('tax'),
            TextColumn::make('total'),
            TextColumn::make('order_no')
                ->label('Order No.')  
                ->sortable()
                ->searchable(),
            TextColumn::make('purchase_method')
                ->label('Payment Method')
                ->sortable(),
            TextColumn::make('transaction.transaction_no')
                ->label('Transaction No.')
                ->sortable()
                ->searchable(),
            BadgeColumn::make('status')
                ->enum([
                    1 => 'Success',
                    2 => 'Failed',
                    3 => 'Awaiting Payment',
                ])
                ->colors([
                    'success' => 1,
                    'danger' => 2,
                    'warning' => 3,
                ])
                ->sortable(),           
            TextColumn::make('remarks'),
        ];
    }

    protected function getTableQuery(): Builder|Relation
    {
        if ($this->currentRouteId) {
            return MerchantOfferClaim::where('user_id', $this->currentRouteId)->orderBy('created_at', 'desc');
        }
    }
}