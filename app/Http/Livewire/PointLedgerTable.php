<?php

namespace App\Http\Livewire;

use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Action;
use Exception;
use Filament\Notifications\Notification;
use Livewire\Component;
use App\Models\PointLedger;
use App\Models\User;
use App\Services\PointService;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;

class PointLedgerTable extends Component implements HasTable, HasActions
{
    use InteractsWithActions;
    use InteractsWithTable;

    public $currentRouteId;
    protected $pointService;

    public function render()
    {
        return view('livewire.point-ledger-table');
    }

    public function mount($currentRouteId)
    {
        $this->currentRouteId = $currentRouteId;
        $this->pointService = new PointService();
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
        ];
    }

    protected function getTableQuery(): Builder|Relation
    {
        if ($this->currentRouteId) {
            return PointLedger::where('user_id', $this->currentRouteId)
                ->orderBy('created_at', 'asc');
        }
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('revert')
                ->label('Revert')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Revert Transaction')
                ->hidden(fn (PointLedger $record): bool => 
                    str_starts_with($record->title, 'Adjustment:') && auth()->user()->hasRole('super_admin'))
                ->action(function (PointLedger $record): void {
                    $user = User::find($this->currentRouteId);
                    $pointService = new PointService();
                    $currentBalance = $pointService->getBalanceOfUser($user);

                    // Check if debit would make balance negative
                    if ($record->credit && $currentBalance < $record->amount) {
                        Notification::make()
                            ->danger()
                            ->title('Cannot Revert')
                            ->body('The user does not have enough points to revert this credit.')
                            ->send();
                        return;
                    }

                    $title = 'Adjustment: ' . $record->title;
                    $remarks = 'Manual Adjustment';

                    try {
                        if ($record->credit) {
                            // If original was credit, create a debit
                            $pointService->debit(
                                $user,
                                $user,
                                $record->amount,
                                $title,
                                $remarks
                            );

                            Log::info('Reverted credit transaction', [
                                'transaction' => $record->id,
                                'user' => $user->id,
                                'amount' => $record->amount,
                                'current_user' => auth()->id(),
                            ]);
                        } else {
                            // If original was debit, create a credit
                            $pointService->credit(
                                $user,
                                $user,
                                $record->amount,
                                $title,
                                $remarks
                            );

                            Log::info('Reverted debit transaction', [
                                'transaction' => $record->id,
                                'user' => $user->id,
                                'amount' => $record->amount,
                                'current_user' => auth()->id(),
                            ]);
                        }

                        Notification::make()
                            ->success()
                            ->title('Reverted')
                            ->body('The transaction has been reverted. New ledger has been created and latest balance updated.')
                            ->send();
                    } catch (Exception $e) {
                        $this->addError('revert', $e->getMessage());
                    }
                })
        ];
    }
}
