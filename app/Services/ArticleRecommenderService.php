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
        // Apply candidate filters to existing query
        $articles = Article::published()
            ->with(['views' => function ($query) {
                $query->where('user_id', $this->user->id);
            }, 'likes' => function ($query) {
                $query->where('user_id', $this->user->id);
            }, 'comments' => function ($query) {
                $query->where('user_id', $this->user->id);
            }, 'categories', 'imports'])
            ->where('user_id', '!=', $this->user->id)
            ->whereDoesntHave('hiddenUsers', function ($query) {
                $query->where('user_id', $this->user->id);
            })
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
                    'article_id' => $article->id,
                    'user_id' => $this->user->id,
                    'affinity' => $affinity,
                    'weight' => $weight,
                    'score' => $affinity * $weight,
                    'last_built' => Carbon::now()->toDateTimeString(),
                ];
            });

        // save to user->articleRanks update or create
        $this->user->articleRanks()->delete();
        $this->user->articleRanks()->createMany($scored->toArray());

        return $scored;
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
        // increase affinity is user commented
        if ($article->comments_count > 0) {
            $affinity += 10;
        }
        // decrease affinity if user view more than twice
        if ($article->views_count > 2) {
            $affinity -= 15;
        }
        // increase affinity if user never viewed before
        if ($article->views_count == 0) {
            $affinity += 10;
        }
        // categories match user will increase affinity
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
        // the older the article, then lesser weight
        $weight = 10;

        // reduce weight if older the article 30% of entire weight
        $weight -= $weight * ($article->created_at->diffInDays(Carbon::now()) / 100);

        // reduce article weight if article is an imported article. weights the remainder 60%
        if ($article->is_imported) {
            $weight -= $weight * 0.6;
        }

        return $weight;
    }
}
