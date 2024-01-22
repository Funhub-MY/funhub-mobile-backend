<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Console\Command;
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
        ->whereDoesntHave('categories', function ($query) {
            $query->where('parent_id', '!=', null);
        })->get();

        $this->info('All Sub Categories Name: '. $subCategories->pluck('name')->implode(', '));

        $this->info('Articles published that has no sub categories: '. $articles->count());
        // first article
        $articles = $articles->take(5);

        foreach ($articles as $article) {

            $result = $client->chat()->create([
                'model' => 'gpt-3.5-turbo-0613',
                'messages' => [
                    ['role' => 'user', 'content' => 'Categorise content into provided (must use) category list: '. $subCategories->pluck('name')->implode(', '). ' must use and return categories names only separated by comma without space,content: '. $this->generateContent($article)],
                ],
            ]);

            $this->info('Content: ' . $this->generateContent($article));

            $this->info('Result: '. $result->choices[0]->message->content. ' | Total Tokens used: '. $result->usage->totalTokens );

            $this->info('=====================================');
        }

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
