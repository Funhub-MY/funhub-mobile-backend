<?php

namespace App\Console\Commands;

use App\Models\SearchKeyword;
use App\Models\Article;
use Illuminate\Console\Command;
use OpenAI;

class SearchKeywordAutoLink extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:keyword-auto-link';

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
        $client = OpenAI::client(config('services.openai.secret'));

        // combine all keywords into id:keyword-array
        $keywords = SearchKeyword::all()->pluck('keyword', 'id')->toArray();
        $this->info('Total Keywords: ' . count($keywords));

        // get keywords into id:keyword command seperate string
        $keywordsCommand = '';
        foreach($keywords as $id => $keyword) {
            $k = trim($keyword);
            // if latest index dont add ,
            if ($id == array_key_last($keywords)) {
                $keywordsCommand .= $id . ':' . $k;
                continue;
            }
            $keywordsCommand .= $id . ':' . $k . ',';
        }
        $this->info('Keywords Command: ' . $keywordsCommand);

        $totalArticlesProcessed = 0;

        $chunkSize = 200; // Adjust this value based on your memory requirements
        $articles = Article::published()
            ->whereDoesntHave('searchKeywords')
            ->orderBy('created_at', 'DESC')
            ->chunkById($chunkSize, function ($articlesChunk) use ($client, $totalArticlesProcessed, $keywordsCommand) {
                foreach ($articlesChunk as $article) {
                    $content =  $this->generateContent($article);
                    $this->info('---- Article ID: ' . $article->id . ' | Content: ' . $content);
                    $result = $client->chat()->create([
                        'model' => 'gpt-3.5-turbo-0613',
                        'messages' => [
                            ['role' => 'user', 'content' => 'match below keywords (format: id:keyword) to content, return only keywords IDs comma separated'],
                            ['role' => 'user', 'content' => 'keywords: '. $keywordsCommand],
                            ['role' => 'user', 'content' => 'content:'. $content],
                        ],
                    ]);

                    $this->info('\n ---- Result: ' . $result->choices[0]->message->content);

                    $keywordIds = explode(',', $result->choices[0]->message->content);
                     // $article->searchKeywords()->attach($keywordIds);
                    $totalArticlesProcessed++;
                }
            });

        $this->info('Total Articles: ' . $articles->total());


        return Command::SUCCESS;
    }

    private function keywordCities()
    {
       return [
            'kl' => [
                'kuala lumpur',
                '吉隆坡',
            ],
            'subang' => [
                'subang jaya',
                '梳邦再也',
            ],
            'penang' => [
                'penang',
                '槟城',
            ],
            'pj' => [
                'petaling jaya',
                '八打灵再也',
            ],
            'ipoh' => [
                'ipoh',
                '怡保',
            ],
            'bukit jalil' => [
                'bukit jalil',
                '武吉加里尔',
            ],
            'klang' => [
                'klang',
                '巴生',
            ],
            'shah alam' => [
                'shah alam',
                '莎亚南',
            ],
            'ss15' => [
                'ss15',
            ],
            'taipan' => [
                'taipan',
            ],
            'sunway' => [
                'sunway',
                '双威',
            ],
            'puchong' => [
                'puchong',
                '蒲种',
            ],
            'kuching' => [
                'kuching',
                '古晋',
            ],
            'jb' => [
                'johor',
                '柔佛',
            ],
        ];
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
