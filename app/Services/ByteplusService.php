<?php

namespace App\Services;

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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            Log::error('Byteplus workflow error', [
                'message' => $e->getMessage(),
                'params' => $params
            ]);

            return [];
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
