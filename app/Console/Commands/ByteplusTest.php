<?php

namespace App\Console\Commands;

use App\Services\Byteplus\SignatureV4;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class BytePlusTest extends Command
{
    protected $signature = 'byteplus:test {api} {--region=}';

    protected $region = 'ap-singapore-1';

    protected $description = 'Test BytePlus API signature and requests';

    public function handle()
    {
        $this->region = $this->option('region') ?? $this->region;

        $this->info('Region: ' . $this->region);

        if ($this->argument('api') == 'list-space') {
            $this->listVodSpace();
        } elseif ($this->argument('api') == 'upload-video') {
            $this->uploadVideo();
        }
    }

    private function listVodSpace()
    {
        $url = 'https://vod.byteplusapi.com';
        $params = [
            'Action' => 'ListSpace',
            'Version' => '2023-01-01',
        ];

        $signature = $this->generateSignature('GET', $url, $params);

        try {
            $response = Http::withHeaders([
                'Authorization' => $signature['signature'],
                'x-date' => $signature['timestamp'],
            ])->get($url . '?' . http_build_query($params));
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return;
        }

        $this->info('List Space Response:');
        $this->info($response->body());

        $responseBody = json_decode($response->body(), true);
        if (isset($responseBody['Result'])) {
            foreach ($responseBody['Result'] as $key => $value) {
                $this->info('-- Available Space:' . $responseBody['Result'][$key]['SpaceName']);
            }
        }
    }

    private function uploadVideo()
    {
        $videoUrl = $this->ask('video-url');
        $title = $this->ask('Title');
        $description = $this->ask('Description');
        $tags = $this->ask('Tags');
        $spaceName = $this->ask('Space Name');

        $url = 'https://vod.byteplusapi.com';
        $params = [
            'Action' => 'UploadMediaByUrl',
            'Version' => '2023-01-01',
            'SpaceName' => $spaceName,
            'URLSets' => json_encode([
                [
                    'SourceUrl' => $videoUrl,
                    'Title' => $title,
                    'Description' => $description,
                    'Tags' => $tags,
                ],
            ]),
        ];

        $signature = $this->generateSignature('POST', $url, $params);

        try {
            $response = Http::withHeaders([
                'Authorization' => $signature['signature'],
                'x-date' => $signature['timestamp'],
            ])->post($url, $params);
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return;
        }

        $this->info('Upload Media Response:');
        $this->info($response->body());
    }

    private function generateSignature($method, $url, $params)
    {
        $timestamp = gmdate('Ymd\THis\Z');
        $date = substr($timestamp, 0, 8);

        $credentials = [
            'ak' => config('services.byteplus.key'),
            'sk' => config('services.byteplus.secret'),
            'region' => $this->region,
            'service' => 'vod',
        ];

        $this->info('Credentials: ' . json_encode($credentials));

        $signatureV4 = new SignatureV4();

        $uri = new Uri($url);
        $queryString = http_build_query($params);
        $uri = $uri->withQuery($queryString);

        $request = new Request($method, $uri, [
            'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
            'X-Date' => $timestamp,
        ]);

        $signedRequest = $signatureV4->signRequest($request, $credentials);

        return [
            'credentialScope' => $date . '/'.$this->region.'/vod/request',
            'signedHeaders' => 'content-type;host;x-content-sha256;x-date',
            'signature' => $signedRequest->getHeaderLine('Authorization'),
            'timestamp' => $timestamp,
        ];
    }
}
