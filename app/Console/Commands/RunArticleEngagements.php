<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEngagementInteractions;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunArticleEngagements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:run-engagements';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Running article engagements');

        $engagements  = \App\Models\ArticleEngagement::where('scheduled_at', '<=', now())
            ->whereNull('executed_at')
            ->get();

        foreach ($engagements as $engagement) {
            $this->info('[RunArticleEngagements] Processing engagement: ' . $engagement->id);
            Log::info('[RunArticleEngagements] Processing engagement: ' . $engagement->id);

            $users = $engagement->users;
            $articleId = $engagement->article_id;
            $action = $engagement->action;
            $comment = $engagement->comment;

            // immediately mark as executed no matter the below succes or failed
            $engagement->executed_at = now();
            $engagement->save();

            // if only one user then direct execute
            if ($users->count() === 1) {
                $this->info("[RunArticleEngagements] Processing engagement for user ID {$users->first()->id} on article ID {$articleId} with action {$action}");
                Log::info("[RunArticleEngagements] Processing engagement for user ID {$users->first()->id} on article ID {$articleId} with action {$action}");
                ProcessEngagementInteractions::dispatch($engagement->users->first()->id, $articleId, $action, $comment);
                return;
            } else {
                foreach ($users as $user) {
                    $this->info("[RunArticleEngagements] Processing engagement for user ID {$user->id} on article ID {$articleId} with action {$action}");
                    Log::info("[RunArticleEngagements] Processing engagement for user ID {$user->id} on article ID {$articleId} with action {$action}");
                    // random delay between 1min-120hours
                    ProcessEngagementInteractions::dispatch($user->id, $articleId, $action, $comment)
                        ->delay(Carbon::now()->addMinutes(rand(1, 7200)));
                }
            }
        }

        return Command::SUCCESS;
    }
}
