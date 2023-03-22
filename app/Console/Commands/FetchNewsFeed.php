<?php

namespace App\Console\Commands;

use Bmatovu\LaravelXml\LaravelXml;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // fetch news feed by using HTTP guzzle.
        $response = Http::get('https://www.goodymy.com/feed');
        // check response
        if ($response->ok() || $response->status() === 200) {
            // xml body
            //$xml_body = new \SimpleXMLElement($response->body());
            // transform xml into string
            //$string_xml_body = simplexml_load_string($xml_body);
//            $test = $xml_body->xpath('/rss/channel/item/title');
            $test = xml_decode($response->body());
            dd($test['channel']);
            // convert to json
        }
        return true;
    }
}
