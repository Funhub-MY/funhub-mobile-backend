<?php

namespace App\Console\Commands;

use Exception;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Setting;
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
    protected $signature = 'articles:categorize {--dry-run}';

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
        $this->info('Auto Categorization Started');

        $client = OpenAI::client(config('services.openai.secret'));
        $dryRun = $this->option('dry-run');
        $promptSetting = Setting::where('key', 'auto_categorization_prompt')->first();
        $systemPrompt = $promptSetting ? $promptSetting->value : '';

        // get all subcategories
        $subCategories = ArticleCategory::where('parent_id', '!=', null)->get();

        // get articles that are published and has no sub categories
        $articles = Article::published()->whereDoesntHave('imports')
			->whereIn('source', ['mobile', 'backend'])
			->whereDoesntHave('subCategories')
            ->orderBy('created_at', 'DESC') // latest first
            ->limit(100)
            ->get();

        Log::info('[ProcessArticleCategories] Total Articles to Process: ' . $articles->count());

        $this->info('All Sub Categories Name: '. $subCategories->pluck('name')->implode(', '));

        $this->info('Articles published that has no sub categories: '. $articles->count());

        $totalArticlesProcessed = 0;
        $totalSubcategoriesAttached = 0;
        $totalParentCategoriesAttached = 0;
        $totalTokens = 0;

        foreach ($articles as $article) {
            try {
                // sleep for every 5 requests
                if ($totalArticlesProcessed > 0 && $totalArticlesProcessed % 5 == 0) {
                    $this->info('Sleeping for 5 seconds avoid tpm');
                    sleep(5);
                }

                $result = $client->chat()->create([
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'user', 'content' => 'Categorise content into provided (must use) category list: '. $subCategories->pluck('name')->implode(', '). ' must use and return categories names only separated by comma without spac, do not generate any other categories if you are unsure,content: '. $this->generateContent($article)],
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
                            if (!$dryRun) {
                                $article->subCategories()->attach($subCat->id);
                                // refresh article subCategories
                                $article->refresh();
                            }

                            $this->info('--- Article ID:' . $article->id .', Attached Sub Category: '. $subCat->name);
                            Log::info('[ProcessArticleCategories] Article ID:' . $article->id .', Attached Sub Category: '. $subCat->name);
                            $totalSubcategoriesAttached++;
                        }

                        // attach parent
                        if ($article->categories->where('id', $subCat->parent_id)->count() == 0) {
                            if (!$dryRun) {
                                $article->categories()->attach($subCat->parent_id);
                                // refresh article categories
                                $article->refresh();
                            }
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
            } catch (Exception $e) {
                Log::error('Error processing article: '. $article->id);
                $this->error('Error processing article, skipping: '. $article->id);
                Log::error($e->getMessage());
                continue;
            }
        }

        $this->info('Total Articles Processed: '. $totalArticlesProcessed);
        $this->info('Total Subcategories Attached: '. $totalSubcategoriesAttached);
        $this->info('Total Parent Categories Attached: '. $totalParentCategoriesAttached);
        $this->info('Total Tokens Used: '. $totalTokens);

        // log to info
        Log::info('[ProcessArticleCategories] Results Run on '. now()->toDateTimeString(), [
            'total_articles_processed' => $totalArticlesProcessed,
            'total_subcategories_attached' => $totalSubcategoriesAttached,
            'total_parent_categories_attached' => $totalParentCategoriesAttached,
            'total_tokens_used' => $totalTokens
        ]);

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
