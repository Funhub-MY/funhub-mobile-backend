<?php

namespace App\Http\Livewire;

use Livewire\Component;
use OwenIt\Auditing\Models\Audit;
use Illuminate\Support\HtmlString;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Relations\Relation;

class AuditLogTable extends Component implements HasTable
{
    use InteractsWithTable;

    public $currentRouteId;

    public function render()
    {
        return view('livewire.audit-log-table');
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
            TextColumn::make('event'),
            TextColumn::make('created_at'),
            TextColumn::make('old_values')
                ->label('Old Values')
                ->formatStateUsing(function ($state, Model $record) {
                    $oldValuesWithHtml = '';
            
                    if ($record->old_values) {
                        foreach ($record->old_values as $key => $value) {
                            $oldValuesWithHtml .= "<span class='inline-flex items-center rounded-md px-1 py-1 text-xs font-medium bg-gray-300 mr-1'>$key</span><span class='mr-2'></span>$value,</span>";
                        }
                    }

                    return new HtmlString($oldValuesWithHtml);
                }),
            TextColumn::make('new_values')
                ->label('New Values')          
                ->formatStateUsing(function ($state, Model $record) {
                    $newValuesWithHtml = '';
            
                    if ($record->new_values) {
                        foreach ($record->new_values as $key => $value) {
                            $newValuesWithHtml .= "<span class='inline-flex items-center rounded-md px-1 py-1 text-xs font-medium bg-gray-300 mr-1'>$key</span><span class='mr-2'>$value,</span>";
                        }
                    }

                    return new HtmlString($newValuesWithHtml);
                }),
        ];
    }

    protected function getTableQuery(): Builder|Relation
    {
        if ($this->currentRouteId) {
            return Audit::where('user_id', $this->currentRouteId)->orderBy('created_at', 'desc');
        }
    }
}
