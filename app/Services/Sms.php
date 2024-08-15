<?php
namespace App\Services;

use App\Mail\SmsFailureNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Class Sms
 */
class Sms {
    protected $byteplusUrl;
    protected $byteplusUsername;
    protected $byteplusPassword;

    protected $moviderApiUrl;
    protected $moviderKey;
    protected $moviderSecret;

    /**
     * Sms constructor.
     */
    public function __construct($byteplus_credentials, $movider_credentials)
    {
        // byteplus(primary)
        // https://docs.byteplus.com/en/docs/byteplus-sms/docs-openapi-overview
        $this->byteplusUrl = $byteplus_credentials['url'];
        $this->byteplusUsername = $byteplus_credentials['username'];
        $this->byteplusPassword = $byteplus_credentials['password'];

        // movider(backup)
        // https://developer.movider.co/docs/how-to-send-sms-messages-using-curl
        $this->moviderApiUrl = $movider_credentials['api_url'];
        $this->moviderKey = $movider_credentials['key'];
        $this->moviderSecret = $movider_credentials['secret'];
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
        // Try sending SMS using BytePlus
        $byteplusResponse = $this->sendBytePlusSms($to, $message);

        if ($byteplusResponse !== false) {
            return $byteplusResponse;
        }

        // If BytePlus fails, try sending SMS using Movider
        $moviderResponse = $this->sendMoviderSms($to, $message);

        if ($moviderResponse !== false) {
            return $moviderResponse;
        }

        // If both services fail, return false
        return false;
    }

    /**
     * Send SMS using BytePlus
     *
     * @param $to string phone number with country code
     * @param $message  string message to send
     * @return bool|\Psr\Http\Message\ResponseInterface
     */
    protected function sendBytePlusSms($to, $message)
    {
        $params = [
            'PhoneNumbers' => $to,
            'Content' => $message,
            'From' => config('app.name'),
        ];

        try {
            Log::info('Sending SMS using BytePlus', $params);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json;charset=utf-8',
                'Authorization' => 'Basic ' . base64_encode($this->byteplusUsername . ':asdadsasds' . $this->byteplusPassword),
            ])->post($this->byteplusUrl, $params);

            Log::info('BytePlus SMS response', [
                'status' => $response->getStatusCode(),
                'body' => $response->body()
            ]);

            if ($response->status() == 200) {
                return $response->body();
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Error sending SMS using BytePlus: ' . $e->getMessage(), $params);
            $this->sendFailureNotificationEmail('BytePlus', $to, $e->getMessage());
            return false;
        }
    }

    /**
     * Send SMS using Movider
     *
     * @param $to string phone number with country code
     * @param $message  string message to send
     * @return bool|\Psr\Http\Message\ResponseInterface
     */
    protected function sendMoviderSms($to, $message)
    {
        $data = [
            'api_key' => $this->moviderKey,
            'api_secret' => $this->moviderSecret,
            'text' => $message,
            'to' => $to,
            'from' => config('app.name')
        ];

        try {
            Log::info('Sending SMS using Movider', $data);

            $response = Http::asForm()->post($this->moviderApiUrl, $data);

            Log::info('Movider SMS response', [
                'status' => $response->getStatusCode(),
                'body' => $response->body()
            ]);

            if ($response->status() == 200) {
                return $response->body();
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Error sending SMS using Movider: ' . $e->getMessage(), $data);
            $this->sendFailureNotificationEmail('Movider', $to, $e->getMessage());
            return false;
        }
    }

    /**
     * Send failure notification email
     *
     * @param $gateway string SMS gateway name
     * @param $to string phone number
     * @param $errorMessage string error message
     */
    protected function sendFailureNotificationEmail($gateway, $to, $errorMessage)
    {
        $subject = "Failed to send SMS from {$gateway} to number: {$to}";
        $content = "Error message: {$errorMessage}\n\nTimestamp: " . now()->format('Y-m-d H:i:s');

        Mail::to(config('app.tech_support'))->queue(new SmsFailureNotification($subject, $content));
    }
}
