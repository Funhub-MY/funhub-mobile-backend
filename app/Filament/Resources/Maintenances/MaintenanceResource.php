<?php

namespace App\Filament\Resources\Maintenances;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Maintenances\Pages\ListMaintenances;
use App\Filament\Resources\Maintenances\Pages\CreateMaintenance;
use App\Filament\Resources\Maintenances\Pages\EditMaintenance;
use Closure;
use Filament\Forms;
use Filament\Tables;
use App\Models\Maintenance;
use Carbon\CarbonImmutable;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\MaintenanceResource\Pages;
use App\Filament\Resources\MaintenanceResource\RelationManagers;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class MaintenanceResource extends Resource
{
    protected static ?string $model = Maintenance::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Settings';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('start_date')
                    ->withoutSeconds()
                    ->label('Start Date')
                    ->rules([
                        function () {
                            return function (string $attribute, $value, Closure $fail) {
                                if ($value < now()) {
                                    $fail('The :attribute and Time must be more than a minute from now');
                                }
                            };
                        }
                    ]),
                DateTimePicker::make('end_date')
                    ->after('start_date')
                    ->withoutSeconds()
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
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
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
            'index' => ListMaintenances::route('/'),
            'create' => CreateMaintenance::route('/create'),
            'edit' => EditMaintenance::route('/{record}/edit'),
        ];
    }
}
