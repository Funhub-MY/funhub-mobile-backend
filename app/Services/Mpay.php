<?php
namespace App\Services;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Expr\Cast\Double;

class Mpay {
    protected $url, $mid, $hashKey;
    protected $secureHash;

    public function __construct($mid, $hashKey, $fpxOrCardOnly = false)
    {
        if ((config('app.env') == 'production' && !config('app.debug')) || config('app.env') == 'preprod') {
            $this->url = config('services.mpay.prod_url');
        } else {
            // uat mode
            $this->url = config('services.mpay.uat_url');
        }
        $this->mid = $mid;
        $this->hashKey = $hashKey;

        // override mid and hashkey if card/fpx specific
        if ($fpxOrCardOnly) {
            if ($fpxOrCardOnly == 'card') {
                $this->mid = config('services.mpay.mid_card_only');
                $this->hashKey = config('services.mpay.hash_key_card_only');
            } elseif ($fpxOrCardOnly == 'fpx') {
                $this->mid = config('services.mpay.mid_fpx_only');
                $this->hashKey = config('services.mpay.hash_key_fpx_only');
            }
        }
        $this->secureHash = new SecureHash();
    }

    /**
     * Create transaction
     *
     * @param string $invoice_no    Invoice No Eg. EPS00000019410
     * @param float $amount         Amount in RM
     * @param string $desc          Description
     * @param string $redirectUrl   Redirect URL after success/failure
     * @param string $phoneNo       Phone No Eg. 60123456789
     * @param string $email         Email Eg. john@smith.com
     * @param string $paymentType   Payment Type
     * @param string $card          Card Token
     * @param string $uuid          Card Token UUID (Required same as the one used when card enrollment)
     * @param string $param         Merchant have to add in the delimiter “|” to separate each parameter value. Noted: “|” is “7C” in hexadecimal of ASCII code table.
     * @return void
     */
    public function createTransaction(
        string $invoice_no,
        float $amount,
        string $desc,
        string $redirectUrl,
        string $phoneNo = null,
        string $email = null,
        $paymentType = null,
        $cardToken = null,
        $uuid = null,
        $param = null)
    {
        // check if mid and hashKey is set
        if (!$this->mid || !$this->hashKey) {
            throw new Exception('Mpay MID or hash key is not set');
        }

        // convert amount 000000000100 represent RM 1.00
        // 000000000100 = RM 1.00 000000001000 = RM 10.00 000000010000 = RM 100.00
        $amount = str_pad(number_format($amount, 2, '', ''), 12, '0', STR_PAD_LEFT);

        if(!$email) {
            $defaultEmail = $invoice_no . config('app.mpay_default_email_tld');
            Log::info('[MPAY] Email is not set, using default email:'. $defaultEmail);
        }

        if (!$phoneNo) {
            $defaultPhone = config('app.mpay_default_phone');
            Log::info('[MPAY] Phone is not set, using default phone:'. $defaultPhone);
        }

        // check if redirect url is http, if yes, use https
        if (substr($redirectUrl, 0, 4) === 'http') {
            $redirectUrl = str_replace('http://', 'https://', $redirectUrl);
        }

        $data = [
            'url' => $this->url .'/payment/eCommerce',
            'formData' => [
                'secureHash' => $this->generateHashForRequest($this->mid, $invoice_no, $amount),
                'mid' => $this->mid,
                'invno' => $invoice_no,
                'amt' => $amount,
                'desc' => $desc,
                'postURL' => $redirectUrl,
                'callback_url' => secure_url('/payment/callback'),
                'phone' => $phoneNo ? $phoneNo : $defaultPhone,
                'email' => $email ? $email : $defaultEmail,
                'paymentType' => $paymentType ? $paymentType : null,
                'param' => $param,
            ]
        ];

        if ($cardToken) {
            // token format split string by % symbol
            $cardToken = explode('%', $cardToken);

            $data['formData']['specialParam'] = 'cardvault';
            $data['formData']['token'] = $cardToken[0]; // first part of token
            $data['formData']['uuid'] = $uuid; // must be same as during card tokenization/enrolment
        }

        if ($paymentType) {
            $data['paymentType'] = $paymentType;
        }

        Log::info('Mpay create transaction data', $data);

        return $data;
    }


    /**
     * Create card tokenization
     *
     * @param string $uuid
     * @param string $redirectUrl
     * @param string $invno
     * @param string|null $phoneNo
     * @param string|null $email
     * @return void
     */
    public function createCardTokenization(string $uuid, string $redirectUrl, string $invno, string $phoneNo = null, string $email = null)
    {
        // check if mid and hashKey is set
        if (!$this->mid || !$this->hashKey) {
            throw new Exception('Mpay MID or hash key is not set');
        }

        if(!$email) {
            $defaultEmail = $uuid . config('app.mpay_default_email_tld');
            Log::info('[MPAY] Email is not set, using default email:'. $defaultEmail);
        }

        if (!$phoneNo) {
            $defaultPhone = config('app.mpay_default_phone');
            Log::info('[MPAY] Phone is not set, using default phone:'. $defaultPhone);
        }

        // check if redirect url is http, if yes, use https
        if (substr($redirectUrl, 0, 4) === 'http') {
            $redirectUrl = str_replace('http://', 'https://', $redirectUrl);
        }

        $data = [
            'url' => $this->url .'payment/eCommerce',
            'formData' => [
                'secureHash' => $this->generateHashForRequest($this->mid, $invno, '000000000000'),
                'mid' => $this->mid,
                'invno' => $invno,
                'amt' => '000000000000',
                'desc' => 'Card Tokenization',
                'postURL' => $redirectUrl,
                'phone' => $phoneNo ? $phoneNo : $defaultPhone,
                'email' => $email ? $email : $defaultEmail,
                'specialParam' => 'cardenrolment',
                'uuid' => $uuid,
            ]
        ];

        Log::info('Mpay create card tokenization data', $data);

        return $data;
    }

    /**
     * Query Card Token
     *
     * @param $uuid
     * @param $invno
     * @return void
     */
    public function queryCardToken($uuid, $invno)
    {
        if (!$this->mid || !$this->hashKey) {
            throw new Exception('Mpay MID or hash key is not set');
        }

        $url = $this->url . '/api/paymentService/queryToken/';
        $data = [
            'secureHash' => $this->generateHashForTokenQuery($uuid, $invno),
            'mid' => $this->mid,
            'invno' => $invno,
            'uuid' => $uuid
        ];

        Log::info('Mpay queryCardToken request', ['url' => $url, 'data' => $data]);

        $response = $this->curlRequest($url, $data);

        Log::info('Mpay queryCardToken response', $response);

        return $response;
    }

    /**
     * Query transaction status by invoice number
     *
     * @param string $invoice_no    Invoice number
     * @param float $amount         Amount in RM
     * @param string|null $authorize Optional - Pass 'authorize' to query capture transaction, leave empty for normal transaction
     * @param string|null $postURL  Optional - URL to receive result from MPAY Payment Gateway
     * @return array Response from MPAY containing transaction details
     * @throws Exception
     */
    public function queryTransaction(string $invoice_no, float $amount, ?string $authorize = null, ?string $postURL = null)
    {
        // check if mid and hashKey is set
        if (!$this->mid || !$this->hashKey) {
            throw new Exception('Mpay MID or hash key is not set');
        }

        // convert amount 000000000100 represent RM 1.00
        $amount = str_pad(number_format($amount, 2, '', ''), 12, '0', STR_PAD_LEFT);

        // Prepare data for query
        $data = [
            'secureHash' => $this->generateHashForRequest($this->mid, $invoice_no, $amount),
            'mid' => $this->mid,
            'invno' => $invoice_no,
            'amt' => $amount,
        ];

        // Add optional parameters if provided
        if ($authorize) {
            $data['authorize'] = $authorize;
        }

        if ($postURL) {
            $data['postURL'] = $postURL;
        }

        try {
            $url = $this->url . '/api/paymentService/queryTransaction';
            Log::info('[MPAY] Query transaction request', ['url' => $url, 'data' => $data]);
            
            $response = $this->curlRequest($url, $data);

            Log::info('[MPAY] Query transaction response', $response);

            $response = json_decode($response['body'], true);

            if (isset($response['responseCode'])) {
                Log::info('[MPAY] Query transaction response', ['response' => $response]);
                
                return $response;
            } 
            
            throw new Exception('Failed to query transaction: Invalid response format');
        } catch (Exception $e) {
            Log::error('[MPAY] Query transaction failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check available payment types
     *
     * @return array
     */
    public function checkAvailablePaymentTypes()
    {
        $url = rtrim(trim($this->url), '/') . '/api/paymentService/checkPaymentType/';

        $data = [
            'mid' => $this->mid,
            'secureHash' => $this->generateHashForCheckPaymentType(),
        ];

        Log::info('Mpay checkPaymentType request', ['url' => $url, 'data' => $data]);

        $response = $this->curlRequest($url, $data);

        Log::info('Mpay checkPaymentType response', $response);

        if (isset($response['error'])) {
            return [];
        }

        $responseData = json_decode($response['body'], true);
        if ($response['status'] == 200 && isset($responseData['responseCode']) && $responseData['responseCode'] == '00' && isset($responseData['paymentTypeList'])) {
            $paymentTypes = explode(',', $responseData['paymentTypeList']);
            return array_map(function ($paymentType) {
                return trim(str_replace(['[', ']'], '', $paymentType));
            }, $paymentTypes);
        } else {
            Log::error('Error checking available payment types', [
                'status' => $response['status'],
                'response' => $responseData
            ]);
            return [];
        }
    }

    private function generateHashForCheckPaymentType()
    {
        $string = $this->hashKey . 'Continue' . $this->mid;
        Log::info('Mpay generateHashForCheckPaymentType', [
            'string' => $string,
        ]);
        return $this->secureHash->generateSecureHash($string);
    }

    private function generateHashForTokenQuery($uuid, $invno)
    {
        // hash key + "Continue" + mid + uuid + invno
        $string = $this->hashKey . 'Continue' . $this->mid . $uuid . $invno;

        Log::info('Mpay generateHashForTokenQuery', [
            'string' => $string,
        ]);
        return $this->secureHash->generateSecureHash($string);
    }

    /**
     * Generate hash for request
     *
     * @param string $mid
     * @param string $invoice_no
     * @param float $amount
     * @return string
     */
    public function generateHashForRequest($mid, $invoice_no, $amount)
    {
        // append hashKey,"Continue",mid,invoice_no,amount into a string and call gensecurehash
        $string = strval($this->hashKey) . 'Continue' . $mid . $invoice_no . strval($amount);
        return $this->secureHash->generateSecureHash($string);
    }

    /**
     * Generate hash for response
     *
     * @param string $mid
     * @param string $responseCode
     * @param string $authCode
     * @param string $invoice_no
     * @param float $amount
     * @return string
     */
    public function generateHashForResponse($mid, $responseCode, $authCode, $invoice_no, $amount)
    {
        // append hashkey,mid,responseocde,authcode,invoice_no,amount into a string and call gensecurehash
        $string = $this->hashKey . $mid . $responseCode . $authCode . $invoice_no . $amount;
        return $this->secureHash->generateSecureHash($string);
    }

    /**
     * cURL request
     * We implemented this due to inconsistent guzzle http package usage of uri causing issues like URL not valid
     *
     * @param [type] $url
     * @param [type] $data
     * @param string $method
     * @return void
     */
    private function curlRequest($url, $data, $method = 'POST')
    {
        $url = trim($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Log::error('Invalid URL in curlRequest', ['url' => $url]);
            return ['error' => 'Invalid URL'];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            // CURLOPT_SSL_VERIFYPEER => false, // Only for testing, remove in production
            // CURLOPT_SSL_VERIFYHOST => false, // Only for testing, remove in production
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            Log::error('cURL error in curlRequest', [
                'error' => $error,
                'url' => $url,
                'data' => $data,
                'curl_info' => curl_getinfo($ch)
            ]);
            curl_close($ch);
            return ['error' => $error];
        }

        curl_close($ch);

        return [
            'status' => $httpCode,
            'body' => $response
        ];
    }
}
