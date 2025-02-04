<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Article;
use App\Models\VideoJob;

class UpdateArticlesAbrSearchable extends Command
{
    protected $signature = 'articles:update-abr-searchable {--chunk=10}';
    protected $description = 'Update searchable index for articles with ABR links';

    public function handle()
    {
        $chunkSize = $this->option('chunk');
        
        $totalCount = Article::whereHas('media', function($query) {
            $query->whereHas('videoJob', function($q) {
                $q->where('status', VideoJob::STATUS_COMPLETED)
                  ->whereRaw("JSON_EXTRACT(results, '$.playback_links.abr') IS NOT NULL");
            });
        })->count();

        $this->info("Found {$totalCount} articles with ABR links to update");
        $bar = $this->output->createProgressBar($totalCount);

        Article::whereHas('media', function($query) {
            $query->whereHas('videoJob', function($q) {
                $q->where('status', VideoJob::STATUS_COMPLETED)
                  ->whereRaw("JSON_EXTRACT(results, '$.playback_links.abr') IS NOT NULL");
            });
        })
        ->chunk($chunkSize, function($articles) use ($bar) {
            foreach ($articles as $article) {
                $article->searchable();
                $bar->advance();
            }
            
            // Clear memory after each chunk
            gc_collect_cycles();
        });

        $bar->finish();
        $this->newLine();
        $this->info('Articles updated successfully');
    }
}
