<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEngagementInteractions;
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
            ProcessEngagementInteractions::dispatch($engagement);
        }

        return Command::SUCCESS;
    }
}
