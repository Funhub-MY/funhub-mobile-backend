<?php

namespace App\Console\Commands;

use App\Models\RssChannel;
use App\Services\GoodyMyRssService;
use App\Services\MortifyRssService;
use App\Services\NoodouRssService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use App\Services\Goody25RssService;

class FetchNewsFeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:news-feed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch News Feed from different media.';

    private $article_categories = null;
    private $rss_channels = null;
    private $error_messages = [];
    protected $goody25_service = null;
    protected $goodymy_service = null;
    protected $mortify_service = null;
    protected $noodou_service = null;

    /**
     * Execute the console command.
     *
     * @return bool
     */
    public function __construct()
    {
        parent::__construct();
        $this->goody25_service = new Goody25RssService();
        $this->goodymy_service = new GoodyMyRssService();
        $this->mortify_service = new MortifyRssService();
        $this->noodou_service = new NoodouRssService();
    }

    public function handle(): bool
    {
        // record time used.
        $start = now();
        $this->line('Processing Fetch news feed...');
        $this->line('Getting all Rss Channels...');
        $this->rss_channels = $this->prepareRssChannels();
        $this->info('Fetched RSS Channel');
        if (count($this->rss_channels) > 0) {
            foreach($this->rss_channels as $channel) {
                if ($channel->channel_name == 'Goody25') {
                    $this->info('Fetching RSS Feeds from channel: ' . $channel->channel_name);
                    $this->goody25_service->fetchRSS($channel);
                }
                if ($channel->channel_name == 'Goody25') {
                    $this->info('Fetching RSS Feeds from channel: '.$channel->channel_name);
                    $this->goody25_service->fetchRSS($channel);
                } else if ($channel->channel_name == 'GoodyMy') {
                    $this->info('Fetching RSS Feeds from channel: '.$channel->channel_name);
                    $this->goodymy_service->fetchRSS($channel);
                }
                else if($channel->channel_name == 'Mortify') {
                    $this->info('Fetching RSS Feeds from channel: '.$channel->channel_name);
                    $this->mortify_service->fetchRSS($channel);
                } else if ($channel->channel_name == 'Noodou') {
                    $this->info('Fetching RSS Feeds from channel: '.$channel->channel_name);
                    $this->noodou_service->fetchRSS($channel);
                }
            }
        } else {
            $this->line('There are no RSS Channel in the DB.');
        }

        $time = $start->diffInSeconds(now());
        $this->line('Finished processing in '. $time .' seconds');
        return true;
    }

    public function prepareRssChannels() : Collection
    {
        return RssChannel::all();
    }
}
