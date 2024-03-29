<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Transaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Relations\Relation;

class TransactionHistoryTable extends Component implements HasTable
{
    use InteractsWithTable;

    public $currentRouteId;

    public function render()
    {
        return view('livewire.transaction-history-table');
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
            TextColumn::make('transaction_no')
                ->label('Transaction No.')
                ->sortable()
                ->searchable(),
            TextColumn::make('transactionable.name')
                ->label('Item Name')
                ->sortable()
                ->searchable(),
            TextColumn::make('amount'),
            TextColumn::make('gateway')
                ->sortable(),
            TextColumn::make('gateway_transaction_id')
                ->label('Gateway Transaction ID')
                ->sortable()
                ->searchable(),
            BadgeColumn::make('status')
                ->enum([
                    0 => 'Pending',
                    1 => 'Success',
                    2 => 'Failed',
                ])
                ->colors([
                    'warning' => 0,
                    'success' => 1,
                    'danger' => 2,
                ])
                ->sortable(),
            TextColumn::make('payment_method')
                ->label('Payment Method')
                ->sortable(),
            TextColumn::make('bank')
                ->sortable(),
            TextColumn::make('card_last_four')
                ->label('Card Last 4 Digits'),
            TextColumn::make('card_type')
                ->label('Card Type')
                ->sortable(),
            TextColumn::make('created_at')
                ->dateTime()
                ->sortable(),
        ];
    }

    protected function getTableQuery(): Builder|Relation
    {
        if ($this->currentRouteId) {
            return Transaction::where('user_id', $this->currentRouteId)->orderBy('created_at', 'desc');
        }
    }
}
