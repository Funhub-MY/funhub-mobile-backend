<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\PointLedger;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Relations\Relation;

class PointLedgerTable extends Component implements HasTable
{
    use InteractsWithTable;

    public $currentRouteId;

    public function render()
    {
        return view('livewire.point-ledger-table');
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
            TextColumn::make('title')
                ->label('Item')
                ->sortable()
                ->searchable(),
            TextColumn::make('pointable.name')
                ->label('Item Name')
                ->sortable()
                ->searchable(),
            TextColumn::make('amount'),
            IconColumn::make('credit')
                ->options([
                    'heroicon-o-x-circle' => 0,
                    'heroicon-o-check-circle' => 1,
                ])
                ->colors([
                    'danger' => 0,
                    'success' => 1,
                ])
                ->sortable(),
            IconColumn::make('debit')
                ->options([
                    'heroicon-o-x-circle' => 0,
                    'heroicon-o-check-circle' => 1,
                ])
                ->colors([
                    'danger' => 0,
                    'success' => 1,
                ])
                ->sortable(),
            TextColumn::make('balance'),
            TextColumn::make('remarks'),
            TextColumn::make('created_at')
                ->dateTime()
                ->sortable(),
        ];
    }

    protected function getTableQuery(): Builder|Relation
    {
        if ($this->currentRouteId) {
            return PointLedger::where('user_id', $this->currentRouteId)->orderBy('created_at', 'desc');
        }
    }
}
