<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use Filament\Widgets\BarChartWidget;
use Illuminate\Database\Eloquent\Scopes\SoftDeletingScope;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class ArticleCountChartOverMonths extends BarChartWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Articles';

    protected function getData(): array
    {
        $publishedData = Article::withoutGlobalScope(SoftDeletingScope::class)
            ->published()
            ->where('created_at', '>=', now()->subMonths(12))
            ->get();

        $unpublishedData = Article::withoutGlobalScope(SoftDeletingScope::class)
            ->where('status', Article::STATUS_DRAFT)
            ->where('created_at', '>=', now()->subMonths(12))
            ->get();

        $publishedGrouped = $publishedData->groupBy(fn ($article) => $article->created_at->format('M'))
            ->map(fn ($group) => $group->count());

        $unpublishedGrouped = $unpublishedData->groupBy(fn ($article) => $article->created_at->format('M'))
            ->map(fn ($group) => $group->count());

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        $published = collect($months)->mapWithKeys(fn ($month) => [$month => $publishedGrouped->get($month, 0)])->values()->toArray();
        $unpublished = collect($months)->mapWithKeys(fn ($month) => [$month => $unpublishedGrouped->get($month, 0)])->values()->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Published',
                    'data' => $published,
                    'backgroundColor' => '#3490dc',
                ],
                [
                    'label' => 'Unpublished',
                    'data' => $unpublished,
                    'backgroundColor' => '#f6993f',
                ],
            ],
            'labels' => $months,
        ];
    }
}