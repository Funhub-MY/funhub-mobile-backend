<?php

namespace App\Console\Commands;

use Exception;
use App\Services\Byteplus\SignatureV4;
use AWS\CRT\Log;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log as FacadesLog;

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
        } elseif ($this->argument('api') == 'query-upload') {
            $jobId = $this->ask('Job ID');
            $this->queryUploadTask($jobId);
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
        } catch (Exception $e) {
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

        // All parameters should be in query string
        $params = [
            'Action' => 'UploadMediaByUrl',
            'Version' => '2023-01-01',
            'SpaceName' => $spaceName,
            'URLSets' => json_encode([[
                'SourceUrl' => $videoUrl,
                'Title' => $title,
                'Description' => $description,
                'Tags' => $tags,
            ]])
        ];

        $signature = $this->generateSignature('GET', $url, $params);

        try {
            $response = Http::withHeaders([
                'Authorization' => $signature['signature'],
                'x-date' => $signature['timestamp'],
            ])->get($url . '?' . http_build_query($params));

            $this->info('Upload Media Response:');
            $this->info($response->body());
        } catch (Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return;
        }
    }

    private function queryUploadTask($jobId)
    {
        $url = 'https://vod.byteplusapi.com';

        // Set up query parameters
        $params = [
            'Action' => 'QueryUploadTaskInfo',
            'Version' => '2023-01-01',
            'JobIds' => $jobId
        ];

        // Generate signature
        $signature = $this->generateSignature('GET', $url, $params);

        try {
            $response = Http::withHeaders([
                'Authorization' => $signature['signature'],
                'x-date' => $signature['timestamp'],
            ])->get($url . '?' . http_build_query($params));

            $this->info('Query Upload Task Response:');
            $this->info($response->body());

            // Parse and display relevant information
            $responseBody = json_decode($response->body(), true);
            if (isset($responseBody['Result']['Data']['MediaInfoList'])) {
                foreach ($responseBody['Result']['Data']['MediaInfoList'] as $mediaInfo) {
                    $this->info('-- Upload Status:');
                    $this->info('   Job ID: ' . $mediaInfo['JobId']);
                    $this->info('   State: ' . $mediaInfo['State']);
                    $this->info('   Video ID: ' . ($mediaInfo['Vid'] ?? 'N/A'));

                    if (isset($mediaInfo['SourceInfo'])) {
                        $sourceInfo = $mediaInfo['SourceInfo'];
                        $this->info('   File Info:');
                        $this->info('   - Duration: ' . ($sourceInfo['Duration'] ?? 'N/A') . ' seconds');
                        $this->info('   - Resolution: ' . ($sourceInfo['Width'] ?? 'N/A') . 'x' . ($sourceInfo['Height'] ?? 'N/A'));
                        $this->info('   - Format: ' . ($sourceInfo['Format'] ?? 'N/A'));
                        $this->info('   - Bitrate: ' . ($sourceInfo['Bitrate'] ?? 'N/A') . ' Kbps');
                    }
                }
            }

            if (isset($responseBody['Result']['Data']['NotExistJobIds']) && !empty($responseBody['Result']['Data']['NotExistJobIds'])) {
                $this->error('Non-existent Job IDs:');
                foreach ($responseBody['Result']['Data']['NotExistJobIds'] as $jobId) {
                    $this->error('- ' . $jobId);
                }
            }

        } catch (Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return;
        }
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
