<?php

namespace App\Services;

use Exception;
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
        $this->authorization    = 'Bearer '.config('services.merchantportal.key');
        $this->domain           = config('services.merchantportal.domain');
        $this->header           = [
            'Authorization' => $this->authorization,
            'Accept' => 'application/json',
        ];
    }

    //  Send approval signal to merchant portal
    public function approve($id)
    {
        return $this->callAPI($this->domain."/api/v1/external/merchant/approve", "POST", "Approve merchant", ['id' => $id]);
    }

    //  Send reject signal to merchant portal
    public function reject($id)
    {
        return $this->callAPI($this->domain."/api/v1/external/merchant/reject", "POST", "Reject merchant", ['id' => $id]);
    }

    //  Trigger the merchant portal to send login email 
    public function sendLoginEmail($merchantIds)
    {
        return $this->callAPI($this->domain."/api/v1/external/merchant/sendLoginEmail", "POST", "Send Login Email to merchant users", ['ids' => $merchantIds]);
    }

    //  Call the signal to merchant portal to sync the data with base portal
    public function syncMerchant($id)
    {
        return $this->callAPI($this->domain."/api/v1/external/merchant/sync", "POST", "Sync merchant", ['id' => $id]);
    }

    //  Call the signal to merchant portal to sync the data with base portal
    public function syncStore($id)
    {
        return $this->callAPI($this->domain."/api/v1/external/store/sync", "POST", "Sync store", ['id' => $id]);
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

        } catch (Exception $e) {
            // Log the error and return a response
            Log::error('['.$action.'] API request failed: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => 'Server error. Please try again later.'
            ];
        }
    }
}




