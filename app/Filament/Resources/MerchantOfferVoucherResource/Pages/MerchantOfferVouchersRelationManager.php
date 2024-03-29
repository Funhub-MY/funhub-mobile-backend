<?php

namespace App\Filament\Resources\MerchantOfferVoucherResource\Pages;

use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Arr;
use Filament\Resources\Form;
use Filament\Resources\Table;
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
    public static function table(Table $table): Table
    {
        return $table
            ->columns(Arr::flatten([
                Tables\Columns\TextColumn::make('user.name')
                    ->label(trans('filament-auditing::filament-auditing.column.user_name')),
                Tables\Columns\TextColumn::make('event')
                    ->label(trans('filament-auditing::filament-auditing.column.event')),
                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->label(trans('filament-auditing::filament-auditing.column.created_at')),
                Tables\Columns\ViewColumn::make('old_values')
                    ->view('filament-auditing::tables.columns.key-value')
                    ->label(trans('filament-auditing::filament-auditing.column.old_values')),
                Tables\Columns\ViewColumn::make('new_values')
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
            ->actions([
                Tables\Actions\Action::make('restore')
                    ->label(trans('filament-auditing::filament-auditing.action.restore'))
                    ->action(fn (Audit $record) => static::restoreAuditSelected($record))
                    ->icon('heroicon-o-refresh')
                    ->requiresConfirmation()
                    ->visible(fn (Audit $record, RelationManager $livewire): bool => auth()->user()->can('restoreAudit', $livewire->ownerRecord) && $record->event === 'updated' && static::shouldAllowRestoreAudit($livewire->ownerRecord))
                    ->after(function ($livewire) {
                        $livewire->emit('auditRestored');
                    }),
            ])
            ->bulkActions([
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
