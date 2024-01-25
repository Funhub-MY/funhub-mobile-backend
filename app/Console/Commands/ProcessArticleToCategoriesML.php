<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use OpenAI;
use OpenAI\Client;

class ProcessArticleToCategoriesML extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:categorize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Categorieses articles into its correct category based on its content and OpenAI API';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $client = OpenAI::client(config('services.openai.secret'));

        // get all subcategories
        $subCategories = ArticleCategory::where('parent_id', '!=', null)->get();

        // get articles that are published and has no sub categories
        $articles = Article::published()->whereDoesntHave('imports')
            ->whereDoesntHave('subCategories')
            ->get();

        $this->info('All Sub Categories Name: '. $subCategories->pluck('name')->implode(', '));

        $this->info('Articles published that has no sub categories: '. $articles->count());
        // first article
        $articles = $articles->take(10);

        $totalArticlesProcessed = 0;
        $totalSubcategoriesAttached = 0;
        $totalParentCategoriesAttached = 0;
        $totalTokens = 0;

        foreach ($articles as $article) {
            // sleep for every 5 requests
            if ($totalArticlesProcessed > 0 && $totalArticlesProcessed % 5 == 0) {
                $this->info('Sleeping for 20 seconds avoid tpm');
                sleep(20);
            }

            $result = $client->chat()->create([
                'model' => 'gpt-3.5-turbo-0613',
                'messages' => [
                    ['role' => 'user', 'content' => 'Categorise content into provided (must use) category list: '. $subCategories->pluck('name')->implode(', '). ' must use and return categories names only separated by comma without space,content: '. $this->generateContent($article)],
                ],
            ]);

            // $this->info('Content: ' . $this->generateContent($article));

            $this->info('Article ID:' . $article->id .', Result: '. $result->choices[0]->message->content. ' | Total Tokens used: '. $result->usage->totalTokens );
            Log::info('[ProcessArticleCategories] Article ID:' . $article->id .', Result: '. $result->choices[0]->message->content. ' | Total Tokens used: '. $result->usage->totalTokens );

            $totalTokens += $result->usage->totalTokens;

            $resultCategories = explode(',', $result->choices[0]->message->content);
            foreach ($resultCategories as $resultCategory) {
                $resultCategory = trim($resultCategory);
                $subCat = $subCategories->where('name', $resultCategory)->first();
                if ($subCat) {
                    // attach if dosent exist
                    if ($article->subCategories->where('id', $subCat->id)->count() == 0) {
                        $article->subCategories()->attach($subCat->id);
                        // refresh article subCategories
                        $article->refresh();

                        $this->info('--- Article ID:' . $article->id .', Attached Sub Category: '. $subCat->name);
                        Log::info('[ProcessArticleCategories] Article ID:' . $article->id .', Attached Sub Category: '. $subCat->name);
                        $totalSubcategoriesAttached++;
                    }

                    // attach parent
                    if ($article->categories->where('id', $subCat->parent_id)->count() == 0) {
                        $article->categories()->attach($subCat->parent_id);
                        // refresh article categories
                        $article->refresh();
                        $this->info('--- Article ID:' . $article->id .', Attached Parent Category: '. $subCat->parent->name);
                        Log::info('[ProcessArticleCategories] Article ID:' . $article->id .', Attached Parent Category: '. $subCat->parent->name);
                        $totalParentCategoriesAttached++;
                    }
                } else {
                    // category not found
                    $this->info('--- Article ID:' . $article->id .', Category not found: '. $resultCategory);
                }
            }

            $totalArticlesProcessed++;
        }

        $this->info('Total Articles Processed: '. $totalArticlesProcessed);
        $this->info('Total Subcategories Attached: '. $totalSubcategoriesAttached);
        $this->info('Total Parent Categories Attached: '. $totalParentCategoriesAttached);
        $this->info('Total Tokens Used: '. $totalTokens);

        return Command::SUCCESS;
    }

    private function generateContent($article)
    {
        // combine title and content, remove new lines
        $content = $article->title . ' ' . $article->body;
        $content = str_replace("\n", "", $content);

        // remove more than one space
        $content = preg_replace('/\s+/', ' ', $content);
        return $content;
    }
}
