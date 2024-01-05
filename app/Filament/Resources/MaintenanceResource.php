<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Forms;
use Filament\Tables;
use App\Models\Maintenance;
use Carbon\CarbonImmutable;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\MaintenanceResource\Pages;
use App\Filament\Resources\MaintenanceResource\RelationManagers;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class MaintenanceResource extends Resource
{
    protected static ?string $model = Maintenance::class;

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('start_date')
                    ->minDate(now()->addDay()->startOfDay())
                    ->helperText('System will change maintenance status to active at 00:01 of selected date.')
                    ->label('Start Date'),
                DatePicker::make('end_date')
                    ->minDate(now()->addDay()->startOfDay())
                    ->dehydrateStateUsing(function ($state) {
                        return CarbonImmutable::parse($state)->endOfDay();
                    })
                    ->helperText('Maintenance status will be deactivated after 23:59:59 of the selected date.')
                    ->label('End Date'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('start_date')
                    ->label('Start Date')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End Date')
                    ->sortable(),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->formatStateUsing(function ($state, Model $record) {
                        $state_html = '';

                        if (!$record->is_active && $record->end_date < now()) {
                            $state_html = '
                            <div class="filament-status-column">
                                    <div class="min-h-6 inline-flex items-center justify-center space-x-1 whitespace-nowrap rounded-xl px-2 py-0.5 text-sm font-medium tracking-tight rtl:space-x-reverse text-gray-700 bg-gray-500/10">
                                        Passed
                                    </div>
                            </div>';
                        } elseif (!$record->is_active && $record->end_date > now()) {
                                $state_html = '
                                <div class="filament-status-column">
                                        <div class="min-h-6 inline-flex items-center justify-center space-x-1 whitespace-nowrap rounded-xl px-2 py-0.5 text-sm font-medium tracking-tight rtl:space-x-reverse text-warning-700 bg-warning-500/10">
                                            Scheduled
                                        </div>
                                </div>';
                        } else {
                            $state_html = '
                                <div class="filament-status-column">
                                        <div class="min-h-6 inline-flex items-center justify-center space-x-1 whitespace-nowrap rounded-xl px-2 py-0.5 text-sm font-medium tracking-tight rtl:space-x-reverse text-success-700 bg-success-500/10">
                                            Active
                                        </div>
                                </div>';
                        }

                        return new HtmlString($state_html);
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaintenances::route('/'),
            'create' => Pages\CreateMaintenance::route('/create'),
            'edit' => Pages\EditMaintenance::route('/{record}/edit'),
        ];
    }
}
