<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\Byteplus\SignatureV4;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;

class ByteplusService
{
    private string $baseUrl = 'https://vod.byteplusapi.com';
    private string $region;

    public function __construct()
    {
        $this->region = config('services.byteplus.vod_region');
    }

    /**
     * Upload Media by URL
     *
     * @param string $sourceUrl
     * @param string $title
     * @return array
     */
    public function uploadMediaByUrl(string $sourceUrl, string $title): array
    {
        $params = [
            'Action' => 'UploadMediaByUrl',
            'Version' => '2023-01-01',
            'SpaceName' => config('services.byteplus.vod_space'),
            'URLSets' => json_encode([[
                'SourceUrl' => $sourceUrl,
                'Title' => $title,
            ]])
        ];

        try {
            $signature = $this->generateSignature('GET', $this->baseUrl, $params);

            $response = Http::withHeaders([
                'Authorization' => $signature['signature'],
                'x-date' => $signature['timestamp'],
            ])->get($this->baseUrl . '?' . http_build_query($params));

            if ($response->successful()) {
                return $response->json()['Result']['Data'][0] ?? [];
            }

            Log::error('Byteplus upload failed', [
                'response' => $response->json(),
                'params' => $params
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Byteplus upload error', [
                'message' => $e->getMessage(),
                'params' => $params
            ]);

            return [];
        }
    }

    /**
     * Start a Video Processing Workflow
     *
     * @param string $vid
     * @return array
     */
    public function startWorkflow(string $vid): array
    {
        $params = [
            'Action' => 'StartWorkflow',
            'Version' => '2023-01-01',
            'Vid' => $vid,
            'TemplateId' => config('services.byteplus.vod_template_id'),
        ];

        try {
            $signature = $this->generateSignature('GET', $this->baseUrl, $params);

            $response = Http::withHeaders([
                'Authorization' => $signature['signature'],
                'x-date' => $signature['timestamp'],
            ])->get($this->baseUrl . '?' . http_build_query($params));

            if ($response->successful()) {
                return $response->json()['Result'] ?? [];
            }

            Log::error('Byteplus workflow start failed', [
                'response' => $response->json(),
                'params' => $params
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Byteplus workflow error', [
                'message' => $e->getMessage(),
                'params' => $params
            ]);

            return [];
        }
    }

    /**
     * Query Upload Task Info
     *
     * @param string $jobId
     * @return array
     */
    public function queryUploadTaskInfo(string $jobId): array
    {
        $params = [
            'Action' => 'QueryUploadTaskInfo',
            'Version' => '2023-01-01',
            'JobIds' => $jobId
        ];

        try {
            $signature = $this->generateSignature('GET', $this->baseUrl, $params);

            $response = Http::withHeaders([
                'Authorization' => $signature['signature'],
                'x-date' => $signature['timestamp'],
            ])->get($this->baseUrl . '?' . http_build_query($params));

            if ($response->successful()) {
                return $response->json()['Result']['Data'] ?? [];
            }

            Log::error('Byteplus query upload task failed', [
                'response' => $response->json(),
                'jobId' => $jobId
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Byteplus query upload task error', [
                'message' => $e->getMessage(),
                'jobId' => $jobId
            ]);

            return [];
        }
    }

    /**
     * Get Workflow Execution Status
     *
     * @param string $runId
     * @return array
     */
    public function getWorkflowExecution(string $runId): array
    {
        $params = [
            'Action' => 'GetWorkflowExecution',
            'Version' => '2023-01-01',
            'RunId' => $runId
        ];

        try {
            $signature = $this->generateSignature('GET', $this->baseUrl, $params);

            $response = Http::withHeaders([
                'Authorization' => $signature['signature'],
                'x-date' => $signature['timestamp'],
            ])->get($this->baseUrl . '?' . http_build_query($params));

            if ($response->successful()) {
                return $response->json()['Result'] ?? [];
            }

            Log::error('Byteplus get workflow execution failed', [
                'response' => $response->json(),
                'runId' => $runId
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Byteplus get workflow execution error', [
                'message' => $e->getMessage(),
                'runId' => $runId
            ]);

            return [];
        }
    }

    /**
     * Get Video Playback Information
     *
     * @param string $vid
     * @return array
     */
    public function getPlayInfo(string $vid, $customParams = null): array
    {
        $params = $customParams ?? [
            'Action' => 'GetPlayInfo',
            'Version' => '2023-01-01',
            'Vid' => $vid,
            'FileType' => 'video',
            'Definition' => 'auto',
            'Format' => 'hls',
            'Codec' => 'H264',
            'Ssl' => '1'
        ];

        try {
            $signature = $this->generateSignature('GET', $this->baseUrl, $params);

            $response = Http::withHeaders([
                'Authorization' => $signature['signature'],
                'x-date' => $signature['timestamp'],
            ])->get($this->baseUrl . '?' . http_build_query($params));

            if (!$response->successful()) {
                Log::error('Byteplus get play info failed', [
                    'response' => $response->json(),
                    'vid' => $vid
                ]);
                return [];
            }

            $result = $response->json()['Result'] ?? [];

            Log::info('Byteplus get play info response', [
                'response' => $response->json(),
                'vid' => $vid
            ]);

            $playbackLinks = [
                'abr' => null,    // adaptive bit rate with three different streams
                'master_abr' => null, // master adaptive bit rate stream
            ];

            if (isset($result['PlayInfoList']) && is_array($result['PlayInfoList'])) {
                $playbackLinks['abr'] = $result['PlayInfoList'][0]['MainPlayUrl'] ?? null;
                $playbackLinks['master_abr'] = $result['AdaptiveBitrateStreamingInfo']['MainPlayUrl'] ?? null;
                // foreach ($result['PlayInfoList'] as $playInfo) {
                //     switch ($playInfo['Definition'] ?? '') {
                //         case '480p':
                //             $playbackLinks['low'] = $playInfo['MainPlayUrl'] ?? null;
                //             break;
                //         case '720p':
                //             $playbackLinks['medium'] = $playInfo['MainPlayUrl'] ?? null;
                //             break;
                //         case '1080p':
                //             $playbackLinks['high'] = $playInfo['MainPlayUrl'] ?? null;
                //             break;
                //     }
                // }
            } else {
                Log::error('Byteplus get play info error', [
                    'message' => 'No Play Info',
                    'vid' => $vid,
                    'result' => $result
                ]);
            }

            return $playbackLinks;

        } catch (Exception $e) {
            Log::error('Byteplus get play info error', [
                'message' => $e->getMessage(),
                'vid' => $vid
            ]);

            return [];
        }
    }

    /**
     * Publish Video
     *
     * @param string $vid
     * @return bool
     */
    public function publishVideo(string $vid): bool
    {
        $params = [
            'Action' => 'UpdateMediaPublishStatus',
            'Version' => '2023-01-01',
            'Vid' => $vid,
            'Status' => 'Published'
        ];

        try {
            $signature = $this->generateSignature('GET', $this->baseUrl, $params);

            $response = Http::withHeaders([
                'Authorization' => $signature['signature'],
                'x-date' => $signature['timestamp'],
            ])->get($this->baseUrl . '?' . http_build_query($params));

            if (!$response->successful()) {
                Log::error('Byteplus publish video failed', [
                    'response' => $response->json(),
                    'vid' => $vid
                ]);
                return false;
            }

            return true;

        } catch (Exception $e) {
            Log::error('Byteplus publish video error', [
                'message' => $e->getMessage(),
                'vid' => $vid
            ]);

            return false;
        }
    }

    /**
     * Generate signature to use Byteplus API
     *
     * @param String $method
     * @param String $url
     * @param Array $params
     * @return void
     */
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
