<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Route;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;

class ViewUserTable extends Component
{

    public $currentRouteId = null;

    public $activeTab = "1";

    public function render()
    {
        return view('livewire.view-user-table');
    }

    public function mount()
    {
        $current_record = Route::current()->parameters('record');

        if (isset($current_record['record'])) {
            $this->currentRouteId = $current_record['record'];
        }
    }
}
