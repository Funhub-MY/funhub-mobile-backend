<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use Filament\Widgets\BarChartWidget;
use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Facades\Cache;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class ArticleCountChartOverMonths extends BarChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Articles';

    protected function getData(): array
    {
        // get User count across period of 12 months
        $publishedData = Article::withoutGlobalScope(SoftDeletingScope::class)
            ->published()
            ->where('created_at', '>=', now()->subMonths(12))
            ->get();

        $unpublishedData = Article::withoutGlobalScope(SoftDeletingScope::class)
            ->where('status', Article::STATUS_DRAFT)
            ->where('created_at', '>=', now()->subMonths(12))
            ->get();

        $publishedData = $publishedData->groupBy(function ($article) {
            return $article->created_at->format('M');
        })->map(function ($article) {
            return $article->count();
        });

        $unpublishedData = $unpublishedData->groupBy(function ($article) {
            return $article->created_at->format('M');
        })->map(function ($article) {
            return $article->count();
        });

        // fill in empty months with 0
        $months = collect([
            'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
        ]);
        $publishedData = $months->mapWithKeys(function ($month) use ($publishedData) {
            return [$month => $publishedData->get($month, 0)];
        });

        $unpublishedData = $months->mapWithKeys(function ($month) use ($unpublishedData) {
            return [$month => $unpublishedData->get($month, 0)];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Articles Published',
                    'data' => $publishedData->values(),
                    'backgroundColor' => '#3490dc',
                    'stack' => 'stack1', // add stack option
                ],
                [
                    'label' => 'Articles Unpublished',
                    'data' => $unpublishedData->values(),
                    'backgroundColor' => '#f6993f',
                    'stack' => 'stack1', // add stack option
                ],
            ],
            'labels' => $months->values(),
            'options' => [
                'scales' => [
                    'yAxes' => [
                        [
                            'stacked' => true, // add stacked option
                        ],
                    ],
                ],
            ],
        ];
    }
}
