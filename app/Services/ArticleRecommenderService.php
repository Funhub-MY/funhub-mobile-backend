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
        $user_id = $this->user->id;
        $chunkSize = 100;
        $scores = [];

        Article::published()
            ->with(['views', 'likes', 'comments', 'categories', 'imports'])
            ->withCount(['views' => function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            }])
            ->withCount(['likes' => function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            }])
            ->withCount(['comments' => function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            }])
            ->where('user_id', '!=', $this->user->id)
            ->whereDoesntHave('hiddenUsers', function ($query) {
                $query->where('user_id', $this->user->id);
            })
            ->orderBy('created_at', 'desc')
            ->chunk($chunkSize, function ($articles) use (&$scores) {
                foreach ($articles as $article) {
                    $affinity = $this->affinityScore($article);
                    $weight = $this->weightScore($article);
                    array_push($scores, [
                        'article_id' => $article->id,
                        'user_id' => $this->user->id,
                        'affinity' => $affinity,
                        'weight' => $weight,
                        'score' => $affinity * $weight,
                        'last_built' => Carbon::now()->toDateTimeString(),
                    ]);
                }
            });

        Log::info('Finished building recommendations for user ' . $this->user->id, [
            'scored_article_ids ' => Arr::pluck($scores, 'article_id'),
            'scored_article_count' => count($scores),
        ]);

        if ($scores) {
          // delete all article ranks
          $this->user->articleRanks()->delete();
          // recreate many from scores
          $this->user->articleRanks()->createMany($scores);
        }

        // return latest
        return $this->user->articleRanks;
    }

    private function affinityScore($article) {
        $affinity = 0;
        if ($article->likes_count > 0) {
            $affinity += 10;
        }

        if ($article->comments_count > 0) {
            $affinity += 10;
        }

        // Adjusted view count mechanism to incorporate time decay
        if ($article->views_count > 0) {
            // my views
            $latestCreatedAt = $article->views()->where('user_id', $this->user->id)
                ->max('created_at');
            if ($latestCreatedAt) {
                $daysSinceLastView = Carbon::parse($latestCreatedAt)->diffInDays(Carbon::now());

                $viewDecayFactor = 1 / (1 + $daysSinceLastView);
                $affinity += $viewDecayFactor * 10;
            }
        }

        // Incorporating category importance
        foreach ($article->categories as $category) {
            $affinity += $this->hasHighInteractionWithCategory($category) ? 15 : 10;
        }
        return $affinity;
    }

    private function weightScore($article) {
        $weight = 10;

        // if article is older penalize more, we want only latest articles
        $daysSincePublished = Carbon::parse($article->created_at)->diffInDays(Carbon::now());
        $weight -= $daysSincePublished * 0.1;

        if ($article->imports()->exists()) {
            $weight -= $weight * 0.6;
        }
        return $weight;
    }

    /**
    * Determines if the user has a high interaction rate with articles of a particular category.
    *
    * @param $category
    * @return bool
    */
    private function hasHighInteractionWithCategory($category)
    {
        // Define a threshold for what constitutes "high" interaction.
        // This can be based on metrics like views, likes, or comments, and might be adjusted based on data insights.
        $interactionThreshold = 10;

        // Fetch the number of articles from the given category that the user has interacted with.
        // For simplicity, let's assume an interaction constitutes viewing, liking, or commenting on an article.

        $interactions = Article::whereHas('categories', function ($query) use ($category) {
            $query->where('article_category_id', $category->id);
        })
        ->whereHas('views', function ($query) {
            $query->where('user_id', $this->user->id);
        })
        ->orWhereHas('likes', function ($query) {
            $query->where('user_id', $this->user->id);
        })
        ->orWhereHas('comments', function ($query) {
            $query->where('user_id', $this->user->id);
        })
        ->count();

        return $interactions >= $interactionThreshold;
    }
}
