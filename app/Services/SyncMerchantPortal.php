<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

//'1|Uqb14hwgRboVgCWTYTPwc9aCzzmfXIvB0w9hRlTU008eddab';

class SyncMerchantPortal
{
    protected $client;
    protected $authorization;
    protected $domain;
    protected $header;

    public function __construct()
    {
        // Instantiate Guzzle Client
        $this->client           = new Client();
        $this->authorization    = 'Bearer '.env('MERCHANT_PORTAL_API_KEY');
        $this->domain           = env('MERCHANT_PORTAL_DOMAIN');
        $this->header           = [
            'Authorization' => $this->authorization,
            'Accept' => 'application/json',
        ];
    }

    // Send approval signal to merchant portal
    public function approve($id)
    {
        return $this->callAPI($this->domain."/api/v1/external/merchant/approve", "POST", "Approve merchant", ['id' => $id]);
    }

    // Send reject signal to merchant portal
    public function reject($id)
    {
        return $this->callAPI($this->domain."/api/v1/external/merchant/reject", "POST", "Reject merchant", ['id' => $id]);
    }

    private function callAPI($url = "", $method = "", $action = "", $data = []){
        try {
            // Send $method request to external API
            $response = $this->client->request($method, $url, [
                'headers' => $this->header,
                'json' => $data
            ]);

            // Return the response body as an array
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true);

            }else{
                return [
                    'error' => true,
                    'message' => 'Server error. Please try again later.'
                ];
            }

        } catch (\Exception $e) {
            // Log the error and return a response
            Log::error('['.$action.'] API request failed: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => 'Server error. Please try again later.'
            ];
        }
    }
}




