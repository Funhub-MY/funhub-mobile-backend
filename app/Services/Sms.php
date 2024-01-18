<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
            try {
                if ($data['error'] && $data['error']['code'] == 408) {
                    // send email to tech@funhub.my
                    // get current server public ip
                    $ip  = file_get_contents('https://api.ipify.org');
                    Mail::raw('SMS API IP changes, need whitelist :' . $ip , function ($message) use ($ip) {
                        $message->to(config('app.tech_support'))
                            ->subject('[URGENT] SMS API Whitelist New Server IP: ' . $ip);
                    });
                }

                if ($data['error'] && ($data['error']['code'] == 416 || $data['error']['code'] == 417)) {
                    // send email to tech@funhub
                    Mail::raw('SMS API Error, INSUFFICIENT BALANCE' , function ($message) {
                        $message->to(config('app.tech_support'))
                            ->subject('[URGENT] SMS API INSUFFICIENT BALANCE PLEASE TOP UP');
                    });
                }
            } catch (\Exception $e) {
                Log::error('Error sending emails on sms failures' . $e->getMessage());
            }
            return false;
        }

        return $response->getBody()->getContents();
    }
}
