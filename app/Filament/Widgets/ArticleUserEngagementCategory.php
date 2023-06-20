<?php

namespace App\Filament\Widgets;

use App\Models\ArticleCategory;
use Filament\Widgets\RadarChartWidget;

class ArticleUserEngagementCategory extends RadarChartWidget
{
    protected static ?string $heading = 'User Activity by Published Article Category';

    protected function getData(): array
    {       
        $labels = [];
        $viewed_data = [];
        $viewedCategories = ArticleCategory::with(['articles' => function ($query) {
            $query->published()->withCount('views');
        }])
        ->withCount('articles')
        ->get();


        $liked_data = [];
        $likedCategories = ArticleCategory::with(['articles' => function ($query) {
            $query->published()->withCount('likes');
        }])
        ->withCount('articles')
        ->get();

        $comment_data = [];
        $commentedCategories = ArticleCategory::with(['articles' => function ($query) {
            $query->published()->withCount('comments');
        }])
        ->withCount('articles')
        ->get();

        foreach ($viewedCategories as $category) {
            $labels[] = $category->name;
            $viewed_data[] = $category->articles->sum('views_count');
        }

        foreach ($likedCategories as $category) {
            $liked_data[] = $category->articles->sum('likes_count');
        }

        foreach ($commentedCategories as $category) {
            $comment_data[] = $category->articles->sum('comments_count');
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Article Views',
                    'data' => $viewed_data,
                    'backgroundColor' => 'rgba(255, 99, 132, 0.5)',
                ],
                [
                    'label' => 'Article Likes',
                    'data' => $liked_data,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.5)',
                ],
                [
                    'label' => 'Article Comments',
                    'data' => $comment_data,
                    'backgroundColor' => 'rgba(255, 206, 86, 0.5)',
                ],
            ],
            'options' => [
                'scales' => [
                    'r' => [
                        'stacked' => true,
                    ],
                ],
            ],
        ];
    }
}
