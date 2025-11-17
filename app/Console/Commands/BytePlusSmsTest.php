<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class BytePlusSmsTest extends Command
{
    protected $signature = 'byteplus:sms';
    protected $description = 'Send SMS using BytePlus OpenAPI';

    public function handle()
    {
        $this->info('Sending SMS using BytePlus OpenAPI');

        $phoneNumbers = $this->ask('Phone Numbers (comma-separated)');
        $content = $this->ask('Message Content');
        $from = $this->ask('From', 'FUNHUB');
        $tag = $this->ask('Tag (optional)');
        $callbackUrl = $this->ask('Callback URL (optional)');

        $url = 'https://sms.byteplusapi.com/sms/openapi/send_sms';

        $params = [
            'PhoneNumbers' => $phoneNumbers,
            'Content' => $content,
            'From' => $from,
        ];

        if ($tag) {
            $params['Tag'] = $tag;
        }

        if ($callbackUrl) {
            $params['CallbackURL'] = $callbackUrl;
        }

        $username = config('services.byteplus.sms_account');
        $password = config('services.byteplus.sms_password');

        $this->info('-- Username: ' . $username);
        $this->info('-- Password: ' . $password);

        $this->info('-- Request URL: ' . $url);
        $this->info('-- Request Body: ' . json_encode($params));

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json;charset=utf-8',
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
            ])->post($url, $params);
        } catch (Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return;
        }

        $this->info('Send SMS Response:');
        $this->info($response->body());
    }
}
