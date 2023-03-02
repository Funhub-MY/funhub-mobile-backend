<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Class Sms
 */
class Sms {
    protected $apiUrl;
    protected $key;
    protected $secret;
    protected $guzzleClient;

    /**
     * Sms constructor.
     */
    public function __construct($apiUrl, $key, $secret)
    {
        $this->apiUrl = $apiUrl;
        $this->key = $key;
        $this->secret = $secret;
        $this->guzzleClient = new \GuzzleHttp\Client();
    }

    /**
     * Send SMS
     * 
     * @param $to string phone number with country code 
     * @param $message  string message to send
     * @return bool|\Psr\Http\Message\ResponseInterface
     */
    public function sendSms($to, $message)
    {
        // guzzle client post form encoded data to apiUrl
        $data = [
            'api_key' => $this->key,
            'api_secret' => $this->secret,
            'text' => $message,
            'to' => $to,
            'from' => config('app.name')
        ];

        $response = null;
        try {
            Log::info('Sending SMS', $data);

            $response = $this->guzzleClient->post(
                $this->apiUrl,
                [
                    'form_params' => $data,
                    'headers' => [
                        'cache-control'=> 'no-cache',
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ],
                ],
            );
            Log::info('SMS response', [
                'status' => $response->getStatusCode(),
                'body' => $response->getBody()->getContents()
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            Log::error($e->getMessage(), $data);
            return false;
        }

        return $response->getBody()->getContents();
    }
}