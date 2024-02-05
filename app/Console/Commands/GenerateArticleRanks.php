<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserArticleRank;
use App\Services\ArticleRecommenderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateArticleRanks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'article:generate-ranks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate article ranking for users';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // handle all expirewd rankings
        // get all UserArticleRank where generated_at > config('app.recommendation_db_purge_hours')
        $expiredRankings = UserArticleRank::where('last_built', '<', now()->subHours(config('app.recommendation_db_purge_hours')))
            ->with('user')
            ->distinct('user_id')
            ->get();

        $this->info('Expired rankings: ' . $expiredRankings->count());
        Log::info('[GenerateArticleRanks] Expired rankings: ' . $expiredRankings->count());

        // get all user object from related only
        $users = $expiredRankings->pluck('user');

        // rebuild rank for users
        foreach ($users as $user) {
            if ($user) {
                Log::info('[GenerateArticleRanks] Rebuilding recommendations for user ' . $user->id);
                $recommender = new ArticleRecommenderService($user);
                $recommender->build();
            }
        }

        // ------------ get users who didnt get built yet
        $users = UserArticleRank::distinct('user_id')
            ->get();

        $users = User::whereNotIn('id', $expiredRankings->pluck('user_id'))
            ->where('status', User::STATUS_ACTIVE) // only active users
            ->get();

        $this->info('No ranks, users to build: ' . $users->count());
        Log::info('[GenerateArticleRanks] No ranks, users to build: ' . $users->count());

        // build rank for users
        foreach ($users as $user) {
            Log::info('[GenerateArticleRanks] Building recommendations for user ' . $user->id);
            $recommender = new ArticleRecommenderService($user);
            $recommender->build();
        }

        return Command::SUCCESS;
    }
}
