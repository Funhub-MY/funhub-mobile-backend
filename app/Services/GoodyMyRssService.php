<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleImport;
use App\Traits\ArticleSlugTrait;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoodyMyRssService
{
    use ArticleSlugTrait;
    private $error_messages = [];
    protected $article_categories = null;

    public function __construct()
    {
        $this->article_categories = ArticleCategory::Select(DB::raw('id, LOWER(name) as name'))->get();
    }

    public function fetchRSS($channel)
    {
        $import = new ArticleImport();
        // put failed at first.
        $import->status = ArticleImport::IMPORT_STATUS_FAILED;
        $import->rss_channel_id = $channel->id;
        $import->last_run_at = now();
        $import->save();
        try {
            // $response = Http::get($channel->channel_url);
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36'
            ])->get($channel->channel_url);
            // check response
            if ($response->ok() || $response->status() === 200) {
                // string replace from content:encoded to content
                $xml_body = $response->body();
                $xml_body = str_replace('<content:encoded>', '<content>', $xml_body);
                $xml_body = str_replace('</content:encoded>', '</content>', $xml_body);
                $xml_body = str_replace('<media:content ', '<media ', $xml_body);
                $xml_body = str_replace('<media:thumbnail ', '<media-thumbnail ', $xml_body);
                $decoded_body = xml_decode($xml_body);
                $articles = [];
                foreach($decoded_body['channel']['item'] as $item) {
                    $title = $item['title'];
                    $link = $item['link'];
                    // some articles may have more than 1 category.
                    if (is_array($item['category']) && count($item['category']) > 1) {
                        $category = implode(',', $item['category']);
                    } else {
                        $category = $item['category'];
                    }
                    $media = isset($item['media']) ? $item['media']['@attributes']['url'] : null;
                    $media_thumbnail = isset($item['media-thumbnail']) ? $item['media-thumbnail']['@attributes']['url'] : null;
                    $content = $item['content'];
                    $pubDate = $item['pubDate'];
                    $tempArray = array (
                        'title' => $title,
                        'link' => $link,
                        'category' => $category,
                        'media' => $media,
                        'media_thumbnail' => $media_thumbnail,
                        'content' => $content,
                        'pub_date' => $pubDate,
                        'lang' => $decoded_body['channel']['language']
                    );
                    $articles[] = $tempArray;
                }
                // get latest articles
                $articles = $this->sortLatestArticles($articles, $channel);
                // after articles are ready, we proceed to insert the data into our database.
                $is_article_uploaded = $this->processArticlesUpload($articles, $channel, $import);
            } else {
                $import->status = ArticleImport::IMPORT_STATUS_FAILED;
                $this->error_messages[] = $response->body();
                $import->description = $response->body();
                $import->save();
            }
        } catch (BadResponseException $e) {
            Log::info('Channel ID: '.$channel->id .'\n'.'Channel Name: '.$channel->channel_name);
            Log::error($e);
            $import->status = ArticleImport::IMPORT_STATUS_FAILED;
            $this->error_messages[] = $e->getMessage();
            $import->description = $this->error_messages;
            $import->save();
        }
    }
    public function processArticlesUpload($articles = [], $channel = null, $import = null): bool
    {
        if(count($articles) == 0) {
            $import->status = ArticleImport::IMPORT_STATUS_SUCCESS;
            $import->description = ['No new articles found.'];
            $import->save();
            return false;
        }
        // try 2 items first
        foreach($articles as $article) {
            try {
                $new_article = new Article();
                $new_article->title = $article['title'];
                $new_article->body = $article['content'];
                $new_article->slug = $this->formatSlug($article['title']);
                $new_article->type = Article::TYPE[0];
                $new_article->status = array_flip(Article::STATUS)['Draft'];
                $new_article->excerpt = $article['title']; // TODO:: put title at the moment.
                $new_article->user_id = ($channel) ? $channel->user->id : 1;
                //$new_article->user_id = 1;
                $new_article->lang = $article['lang'];
                // save article first as categories and media needed article id to be attached.
                $new_article->save();
                // check categories.
                if(strpos($article['category'], ',')) {
                    // this is to check if it is an array.
                    // make it into array, check if have same category in DB.
                    $categories = explode(',', $article['category']);
                    foreach($categories as $key => $category) {
                        $found = $this->article_categories->where('name', strtolower($category))->first();
                        if ($found == null) {
                            $new_article_category = ArticleCategory::create([
                                'name' => $category,
                                'slug' => Str::slug($category),
                                'lang' => $article['lang'],
                                'user_id' => ($channel) ? $channel->user->id : 1 // at the moment is 1, will be transform it into rss_feed->user->id;
                            ]);
                            $categories[$key] = $new_article_category->id;
                        } else {
                            $categories[$key] = $found->id;
                        }
                    }
                    $article['category'] = $categories;
                    //$article['category'] = implode(',', $categories);
                } else {
                    // if it is not an array, perform categories check as well
                    $found = $this->article_categories->where('name', strtolower($article['category']))->first();
                    if ($found == null) {
                        $new_article_category = ArticleCategory::create([
                            'name' => $article['category'],
                            'slug' => Str::slug($article['category']),
                            'lang' => $article['lang'],
                            'user_id' => ($channel) ? $channel->user->id : 1
                        ]);
                        $article['category'] = array($new_article_category->id);
                        //$article['category'] = $new_article_category->id;
                    } else {
                        //$article['category'] = $found->id;
                        $article['category'] = array($found->id);
                    }
                }
                if ($new_article) {
                    // attach categories
                    $new_article->categories()->attach($article['category']);
                    // attach media
                    if (isset($article['media']) && $article['media'] != null) {
                        $new_article->addMediaFromUrl($article['media'])
                            ->toMediaCollection(Article::MEDIA_COLLECTION_NAME);
                    } else {
                        Log::info('Processing Articles of Channel ID: '.$channel->id .'\n'.'Channel Name: '.$channel->channel_name);
                        Log::error('Article ID: '.$new_article->id. ' does not have media');
                    }
                    if (isset($article['media_thumbnail']) && $article['media_thumbnail'] != null) {
                        $new_article->addMediaFromUrl($article['media_thumbnail'])
                            ->withCustomProperties(['is_cover_picture' => true])
                            ->toMediaCollection(Article::MEDIA_COLLECTION_NAME);
                    } else {
                        Log::info('Processing Articles of Channel ID: '.$channel->id .'\n'.'Channel Name: '.$channel->channel_name);
                        Log::error('Article ID: '.$new_article->id. ' does not have media');
                    }
                    // assign batch import id with articles.
                    $import->articles()->attach($new_article);
                    // force update, as default status is 1. But at this stage it will be 1 as all thing run smoothly.
                    $import->status = ArticleImport::IMPORT_STATUS_SUCCESS;
                    $import->article_pub_date = $articles[0]['pub_date'];
                    $import->save();
                }
            } catch (\Exception $exception) {
                // Log messages
                $this->error_messages[] = $exception->getMessage();
                Log::info('Processing Articles of Channel ID: '.$channel->id .'\n'.'Channel Name: '.$channel->channel_name);
                Log::error($exception->getMessage());
                $import->description = $this->error_messages;
                $import->status = ArticleImport::IMPORT_STATUS_FAILED;
                $import->save();
                continue;
                //return false;
            }
        }
        return true;
    }
    public function sortLatestArticles($articles, $channel) : array
    {
        // get latest import
        $channel_import = ArticleImport::where('rss_channel_id', $channel->id)
            ->where('status', ArticleImport::IMPORT_STATUS_SUCCESS)
            ->whereNotNull('article_pub_date')
            ->orderBy('last_run_at','DESC')
            ->first();
        if (!$channel_import) {
            // if no import has been made, return the entire articles list.
            return $articles;
        }
        $latest_articles = array_filter($articles, function($item) use ($channel_import) {
            // carbon parse string to timestamps first in order to compare.
            $article_date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $item['pub_date']);
            return $article_date->gt($channel_import->article_pub_date);
        });
        return $latest_articles;
    }
}
