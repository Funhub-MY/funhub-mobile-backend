<?php
namespace App\Services;

use App\Models\Article;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ArticleRecommenderService
{
    protected $user;
    public function __construct($user)
    {
        $this->user = $user;
    }

    public function build()
    {
        // if user has articleRanks last_built < 6 hours, then return the articleRanks article ids
        $articleRanks = $this->user->articleRanks()->where('last_built', '>=', Carbon::now()->subHours(config('app.recommendation_db_purge_hours')))->get();
        if ($articleRanks->count() > 0) {
            $scoredIds = $articleRanks->pluck('article_id');
            // shuffle it
            $scoredIds = Arr::shuffle($scoredIds->toArray());
            return $scoredIds;
        }

        // Apply candidate filters to existing query
        $articles = Article::published()
            ->with(['views' => function ($query) {
                $query->where('user_id', $this->user->id);
            }, 'likes' => function ($query) {
                $query->where('user_id', $this->user->id);
            }, 'comments' => function ($query) {
                $query->where('user_id', $this->user->id);
            }, 'categories'])
            ->where('user_id', '!=', $this->user->id)
            ->get();

        // Get ID list
        $ids = $articles->pluck('id');

        // Get affinity and weight scores for each ID
        $scored = Article::whereIn('articles.id', $ids)
            ->withCount(['views', 'likes', 'comments'])
            ->with(['categories' => function ($query) {
                $query->whereIn('article_category_id', $this->user->articleCategoriesInterests()->pluck('article_category_id'));
            }])
            ->get()
            ->map(function ($article) {
                $affinity = $this->affinityScore($article);
                $weight = $this->weightScore($article);

                return [
                    'id' => $article->id,
                    'user_id' => $this->user->id,
                    'affinity' => $affinity,
                    'weight' => $weight,
                    'score' => $affinity * $weight,
                ];
            });

        // Save scores to database for future reference
        $scored->each(function ($score) {
           $this->user->articleRanks()->updateOrCreate([
                'article_id' => $score['id'],
                'affinity' => $score['affinity'],
                'weight' => $score['weight'],
                'score' => $score['score'],
                'last_built' => now(),
            ]);
        });

        // Sort scored results
        $scored = $scored->sortByDesc('score');
        // random shuffle
        $scoredIds = Arr::shuffle($scored->pluck('id')->toArray());
        return $scoredIds;
    }

    /**
     * Calculate affinity score for article
     *
     * @param Article $article
     * @return float
     */
    private function affinityScore($article) {
        $affinity = 0;
        if ($article->likes_count > 0) {
            $affinity += 10;
        }
        $affinity += $article->views_count;
        $affinity -= ($article->views()->where('created_at', '<', now()->subMonth())->count() * 0.1);
        $affinity += $article->categories->count() * 10;

        return $affinity;
    }

    /**
     * Calculate weight score for article
     *
     * @param Article $article
     * @return float
     */
    private function weightScore($article) {
        $weight = $article->created_at->diffInDays(now());
        $weight += $article->comments()->where('created_at', '>', now()->subDay())->count();
        $weight -= $article->created_at->diffInYears(now()) * 0.1;
        $weight += ($article->comments_count / 10);
        return $weight;
    }
}
