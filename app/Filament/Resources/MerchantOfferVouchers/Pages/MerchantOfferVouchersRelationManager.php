<?php

namespace App\Filament\Resources\MerchantOfferVouchers\Pages;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Arr;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\MerchantOfferVoucher;
use OwenIt\Auditing\Contracts\Audit;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Filament\Resources\RelationManagers\RelationManager;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class MerchantOfferVouchersRelationManager extends AuditsRelationManager
{
    public function table(Table $table): Table
    {
        return $table
            ->columns(Arr::flatten([
                TextColumn::make('user.name')
                    ->label(trans('filament-auditing::filament-auditing.column.user_name')),
                TextColumn::make('event')
                    ->label(trans('filament-auditing::filament-auditing.column.event')),
                TextColumn::make('created_at')
                    ->since()
                    ->label(trans('filament-auditing::filament-auditing.column.created_at')),
                ViewColumn::make('old_values')
                    ->view('filament-auditing::tables.columns.key-value')
                    ->label(trans('filament-auditing::filament-auditing.column.old_values')),
                ViewColumn::make('new_values')
                    ->view('filament-auditing::tables.columns.key-value')
                    ->label(trans('filament-auditing::filament-auditing.column.new_values')),
                self::extraColumns()
            ]))
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                Action::make('restore')
                    ->label(trans('filament-auditing::filament-auditing.action.restore'))
                    ->action(fn (Audit $record) => static::restoreAuditSelected($record))
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (Audit $record, RelationManager $livewire): bool => auth()->user()->can('restoreAudit', $livewire->ownerRecord) && $record->event === 'updated' && static::shouldAllowRestoreAudit($livewire->ownerRecord))
                    ->after(function ($livewire) {
                        $livewire->emit('auditRestored');
                    }),
            ])
            ->toolbarActions([
                //
            ]);
    }

    protected static function shouldAllowRestoreAudit($ownerRecord): bool
    {
        $modelClass = get_class($ownerRecord);

        if ($modelClass === MerchantOfferVoucher::class) {
            return false;
        }

        return true;
    }
}
