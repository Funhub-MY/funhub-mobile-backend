<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleImport;
use App\Models\ArticleTag;
use Carbon\Carbon;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Traits\ArticleTrait;

class NoodouRssService
{
    use ArticleTrait;
    private $error_messages = [];

    public function fetchRSS($channel)
    {
        $import = new ArticleImport();
        // put failed at first.
        $import->status = ArticleImport::IMPORT_STATUS_FAILED;
        $import->rss_channel_id = $channel->id;
        $import->last_run_at = now();
        $import->save();
        try {
            // fetch news feed by using HTTP guzzle.
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36'
            ])->get($channel->channel_url);
            // check response
            if ($response->ok() || $response->status() === 200) {
                    $body = new \DOMDocument();
                    $body->loadXML($response->body());
                    $items = $body->getElementsByTagName('item');
                    $articles = [];
                    $article_categories = [];
                    foreach($items as $item) {
                        $media_thumbnail = null;
                        //$content = $this->cleanContentForURL($item->getElementsByTagName('encoded')->item(0)->nodeValue);
                        $content = $item->getElementsByTagName('encoded')->item(0)->nodeValue;
                        $content = $this->cleanContentForURL($content);
                        $tempArray = array (
                            'title' => $item->getElementsByTagName('title')->item(0)->nodeValue,
                            'link' => $item->getElementsByTagName('link')->item(0)->nodeValue,
                            'content' => $content,
                            'pub_date' => $item->getElementsByTagName('pubDate')->item(0)->nodeValue,
                            'lang' => 'zh',
                            'media' => null,
                        );
                        // loop media
                        // then push it into articles array.
                        $media = $item->getElementsByTagName('content');
                        if ($media->length > 0) {
                            $media_children = $media->item(0)->childNodes;
                            foreach($media_children as $child) {
                                if ($child->nodeName == 'media:thumbnail') {
                                    if ($child->attributes && $child->attributes->length > 0) {
                                        $media_thumbnail = $child->attributes->item(0)->nodeValue;
                                    }
                                }
                            }
                        }
                        $tempArray['media_thumbnail'] = $media_thumbnail;
                        // loop category
                        // then push it into articles array.
                        $categories = $item->getElementsByTagName('category');
                        foreach($categories as $category) {
                            $article_categories[] = $category->nodeValue;
                        }
                        $tempArray['tag'] = $article_categories;
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
        $article_tags = ArticleTag::Select(DB::raw('id, LOWER(name) as name'))->get();
        foreach($articles as $article) {
            try {
                $new_article = new Article();
                $new_article->title = htmlspecialchars_decode($article['title']);
                $new_article->body = htmlspecialchars_decode($article['content']);
                // prepare slug text
                $new_article->slug = $this->formatSlug($article['title']);
                $new_article->type = Article::TYPE[0];
                $new_article->status = array_flip(Article::STATUS)['Draft'];
                $new_article->excerpt = $article['title']; // TODO:: put title at the moment.
                $new_article->user_id = ($channel) ? $channel->user->id : 1;
                //$new_article->user_id = 1;
                $new_article->lang = $article['lang'];
                // check categories.
                $tags = $article['tag'];
                foreach($tags as $key => $tag) {
                    $found = $article_tags->where('name', strtolower($tag))->first();
                    if ($found == null) {
                        $new_article_tag = ArticleTag::create([
                            'name' => $tag,
                            'user_id' => ($channel) ? $channel->user->id : 1 // at the moment is 1, will be transform it into rss_feed->user->id;
                        ]);
                        $tags[$key] = $new_article_tag->id;
                    } else {
                        $tags[$key] = $found->id;
                    }
                }
                $article['tag'] = $tags;
                //$article['category'] = implode(',', $categories);
                // save article first as categories and media needed article id to be attached.
                $new_article->save();
                if ($new_article) {
                    // attach categories
                    $new_article->tags()->attach($article['tag']);
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
                    //$import->article_pub_date = $articles[0]['pub_date'];
                    $import->description = ['Successfully imported'];
                    $import->article_pub_date = Carbon::parse($articles[0]['pub_date']);
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
        $channel_import = $this->getChannelLatestImport($channel);
        if (!$channel_import) {
            // if no import has been made, return the entire articles list.
            return $articles;
        }
        $latest_articles = array_filter($articles, function($item) use ($channel_import) {
            // carbon parse string to timestamps first in order to compare.
            $article_date = Carbon::parse($item['pub_date']);
            return $article_date->gt($channel_import->article_pub_date);
        });
        // use array_values to re-index the array.
        return array_values($latest_articles);
    }
    public function getChannelLatestImport($channel = null) : Mixed // return as mixed because it can be collection or null.
    {
        $channel_import = null;
        if ($channel !== null) {
            $channel_import = ArticleImport::where('rss_channel_id', $channel->id)
                ->where('status', ArticleImport::IMPORT_STATUS_SUCCESS)
                ->whereNotNull('article_pub_date')
                ->orderBy('last_run_at','DESC')
                ->first();
        }

        return $channel_import;
    }

}
