<?php

namespace App\Console\Commands;

use App\Services\Byteplus\SignatureV4;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class BytePlusSmsTest extends Command
{
    protected $signature = 'byteplus:sms-test {--region=}';

    protected $region = 'ap-singapore-1';

    protected $description = 'Test BytePlus SMS API';

    private $service = 'volcSMS';

    public function handle()
    {
        $this->region = $this->option('region') ?? $this->region;

        $this->info('Region: ' . $this->region);

        $this->sendSms();
    }

    private function sendSms()
    {
        $smsAccount = $this->ask('SMS Account');
        $templateId = $this->ask('Template ID');
        $templateParam = $this->ask('Template Parameters (JSON)', '{"code":"12345"}');
        $phoneNumbers = $this->ask('Phone Numbers (comma-separated)');
        $tag = $this->ask('Tag (optional)');
        $from = $this->ask('From', 'FUNHUB');

        $url = 'https://sms.byteplusapi.com';
        $queryParams = [
            'Action' => 'SendSms',
            'Version' => '2020-01-01',
        ];
        $params = [
            'SmsAccount' => $smsAccount,
            'TemplateID' => $templateId,
            'TemplateParam' => $templateParam,
            'PhoneNumbers' => $phoneNumbers,
            'From' => $from,
        ];

        if ($tag) {
            $params['Tag'] = $tag;
        }

        $signature = $this->generateSignature('POST', $url . '?' . http_build_query($queryParams), $params);

        $this->info('-- Query Params: \n' . $url . '?' . http_build_query($queryParams));
        $this->info('-- Post Body Params: \n' . json_encode($params));
        $this->info('-- Signature: \n' . json_encode($signature));


        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => $signature['signature'],
                'X-Date' => $signature['timestamp'],
            ])->post($url . '?' . http_build_query($queryParams), $params);
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return;
        }

        $this->info('Send SMS Response:');
        $this->info($response->body());
    }

    private function generateSignature($method, $url, $params)
    {
        $timestamp = gmdate('Ymd\THis\Z');
        $date = substr($timestamp, 0, 8);

        $credentials = [
            'ak' => config('services.byteplus.key'),
            'sk' => config('services.byteplus.secret'),
            'region' => $this->region,
            'service' => $this->service,
        ];

        $this->info('Credentials: ' . json_encode($credentials));

        $signatureV4 = new SignatureV4();

        $uri = new Uri($url);
        $queryString = http_build_query($params);
        $uri = $uri->withQuery($queryString);

        $request = new Request($method, $uri, [
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Date' => $timestamp,
        ]);

        $signedRequest = $signatureV4->signRequest($request, $credentials);

        return [
            'credentialScope' => $date . '/'.$this->region.'/'.$this->service.'/request',
            'signedHeaders' => 'content-type;host;x-content-sha256;x-date',
            'signature' => $signedRequest->getHeaderLine('Authorization'),
            'timestamp' => $timestamp,
        ];
    }
}
