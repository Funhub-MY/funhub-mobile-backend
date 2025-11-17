<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use App\Models\User;
use Closure;
use Filament\Forms\Components\Actions\Modal\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class TopContributor extends BaseWidget
{
    use HasWidgetShield;

    protected function getTableQuery(): Builder
    {
        // exclude user with name like Goody, Moretify, Noodou
        return User::query()
            ->withCount('articles')
            ->orderByDesc('articles_count')
            ->where('name', 'not like', '%Goody%')
            ->where('name', 'not like', '%Moretify%')
            ->where('name', 'not like', '%Noodou%')
            ->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->searchable()
                ->sortable(),
            TextColumn::make('articles_count')
                ->label('Articles Published')
                ->sortable(),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('created_at')
                ->schema([
                    DateTimePicker::make('created_from')->label('From'),
                    DateTimePicker::make('created_until')->label('Until'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['created_from'],
                            fn(Builder $query, $date): Builder => $query->whereHas('articles' , function ($q) use ($date) {
                                $q->whereDate('created_at', '>=', $date);
                            }),
                        )
                        ->when(
                            $data['created_until'],
                            fn(Builder $query, $date): Builder => $query->whereHas('articles' , function ($q) use ($date) {
                                $q->whereDate('created_at', '<=', $date);
                            }),
                        );
                })
        ];
    }

}
