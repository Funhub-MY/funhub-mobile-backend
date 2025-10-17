<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OneSignalSmsService
{
    protected $client;
    protected $appId;
    protected $restApiKey;
    protected $userAuthKey;
    protected $baseUrl = 'https://onesignal.com/api/v1';

    public function __construct()
    {
        $this->client = new Client();
        $this->appId = config('services.onesignal.app_id');
        $this->restApiKey = config('services.onesignal.rest_api_key');
        $this->userAuthKey = config('services.onesignal.user_auth_key');
    }

    /**
     * Send SMS to a phone number
     */
    public function sendSms($phoneNumber, $message)
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/notifications", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . $this->restApiKey,
                ],
                'json' => [
                    'app_id' => $this->appId,
                    'include_phone_numbers' => [$phoneNumber],
                    'contents' => ['en' => $message],
                    'name' => 'SMS Message',
                    'sms_from' => 'FunHubMY',
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('OneSignal SMS Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send OTP via SMS
     */
    public function sendOtp($phoneNumber, $otp)
    {
        $message = "Your OTP verification code is: {$otp}.";

        $result = $this->sendSms($phoneNumber, $message);
        
        if (isset($result['id'])) {
            return [
                'success' => true,
                'notification_id' => $result['id'],
                'phone' => $phoneNumber,
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to send OTP',
        ];
    }

    /**
     * Send SMS to multiple phone numbers
     */
    public function sendBulkSms($phoneNumbers, $message)
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/notifications", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . $this->restApiKey,
                ],
                'json' => [
                    'app_id' => $this->appId,
                    'include_phone_numbers' => $phoneNumbers,
                    'contents' => ['en' => $message],
                    'name' => 'Bulk SMS',
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('OneSignal Bulk SMS Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send SMS with custom sender ID
     */
    public function sendSmsWithSender($phoneNumber, $message, $senderId)
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/notifications", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . $this->restApiKey,
                ],
                'json' => [
                    'app_id' => $this->appId,
                    'include_phone_numbers' => [$phoneNumber],
                    'contents' => ['en' => $message],
                    'sms_from' => $senderId,
                    'name' => 'SMS with Custom Sender',
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('OneSignal SMS Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get SMS delivery status
     */
    public function getSmsStatus($notificationId)
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/notifications/{$notificationId}?app_id={$this->appId}", [
                'headers' => [
                    'Authorization' => 'Basic ' . $this->restApiKey,
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('OneSignal Status Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send transactional SMS (order confirmations, etc.)
     */
    public function sendTransactional($phoneNumber, $templateType, $data)
    {
        $messages = [
            'order_confirmation' => "Order #{$data['order_id']} confirmed! Total: {$data['total']}. Expected delivery: {$data['delivery_date']}.",
            'payment_success' => "Payment of {$data['amount']} received successfully. Transaction ID: {$data['transaction_id']}.",
            'appointment_reminder' => "Reminder: Your appointment is scheduled for {$data['date']} at {$data['time']}.",
            'password_reset' => "Your password reset code is: {$data['code']}. Valid for 15 minutes.",
        ];

        $message = $messages[$templateType] ?? "Notification: " . json_encode($data);
        
        return $this->sendSms($phoneNumber, $message);
    }
}