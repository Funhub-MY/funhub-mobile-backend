<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Expr\Cast\Double;

class Mpay {
    protected $url, $mid, $hashKey;
    protected $secureHash;

    public function __construct($mid, $hashKey, $fpxOrCardOnly = false)
    {
        if (config('app.env') == 'production' && !config('app.debug')) {
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
            throw new \Exception('Mpay MID or hash key is not set');
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
            throw new \Exception('Mpay MID or hash key is not set');
        }

        if(!$email) {
            $defaultEmail = $uuid . config('app.mpay_default_email_tld');
            Log::info('[MPAY] Email is not set, using default email:'. $defaultEmail);
        }

        if (!$phoneNo) {
            $defaultPhone = config('app.mpay_default_phone');
            Log::info('[MPAY] Phone is not set, using default phone:'. $defaultPhone);
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
        // check if mid and hashKey is set
        if (!$this->mid || !$this->hashKey) {
            throw new \Exception('Mpay MID or hash key is not set');
        }

        // api/paymentService/queryToken/
        $url = $this->url . '/api/paymentService/queryToken/';
        $data = [
            'secureHash' => $this->generateHashForTokenQuery($uuid, $invno),
            'mid' => $this->mid,
            'invno' => $invno,
            'uuid' => $uuid
        ];

        Log::info('Mpay queryCardToken data', $data);

        // send post request
        $response = Http::withHeaders([
            'Content-Type' => 'application/json;charset=utf-8',
        ])->post($url, $data);

        $results = json_decode($response->body(), true);

        Log::info('Mpay queryCardToken response', [
            'response' => $results,
        ]);

        return $results;
    }

    /**
     * Check available payment types
     *
     * @return array
     */
    public function checkAvailablePaymentTypes()
    {
        $url = $this->url . '/api/paymentService/checkPaymentType/';

        $data = [
            'mid' => $this->mid,
            'secureHash' => $this->generateHashForCheckPaymentType(),
        ];

        $response = Http::post($url, $data);

        if ($response->successful()) {
            $responseData = $response->json();
            if ($responseData['responseCode'] == '00' && isset($responseData['paymentTypeList'])) {
                // decode [Card, FPX-ABB0234, FPX-Affin Bank, FPX-AGRONet, FPX-Alliance Bank, FPX-AmBank, FPX-Bank Islam, FPX-Bank Muamalat, FPX-Bank Of China, FPX-Bank Rakyat, FPX-BSN, FPX-CIMB Clicks, FPX-Citibank, FPX-Hong Leong Bank, FPX-HSBC Bank, FPX-KFH, FPX-Maybank2U, FPX-OCBC Bank, FPX-Public Bank, FPX-RHB Bank, FPX-SBI Bank A, FPX-SBI Bank B, FPX-SBI Bank C, FPX-Standard Chartered, FPX-UOB Bank, FPX-UOB0229, FPX-B2B-Affin Bank, FPX-B2B-AFFINMAX, FPX-B2B-AGRONetBIZ, FPX-B2B-Alliance Bank, FPX-B2B-AmBank, FPX-B2B-Bank Islam, FPX-B2B-Bank Muamalat, FPX-B2B-Bank Rakyat, FPX-B2B-BNP Paribas, FPX-B2B-CIMB Bank, FPX-B2B-Citibank CorporateBanking, FPX-B2B-Deutsche Bank, FPX-B2B-Hong Leong Bank, FPX-B2B-HSBC Bank, FPX-B2B-KFH, FPX-B2B-LOAD001, FPX-B2B-Maybank2E, FPX-B2B-OCBC Bank, FPX-B2B-PBB0234, FPX-B2B-Public Bank, FPX-B2B-RHB Bank, FPX-B2B-SBI Bank A, FPX-B2B-SBI Bank B, FPX-B2B-SBI Bank C, FPX-B2B-Standard Chartered, FPX-B2B-UOB Bank, FPX-B2B-UOB0228, Boost, GrabPay, TNG]
                // as array
                $paymentTypes = explode(',', $responseData['paymentTypeList']);
                // remove any prefix with [ or suffix ] and trim
                $paymentTypes = array_map(function ($paymentType) {
                    return trim(str_replace(['[', ']'], '', $paymentType));
                }, $paymentTypes);

                return $paymentTypes;
            } else {
                Log::error('Error checking available payment types: ' . $responseData['responseDesc']);
                return [];
            }
        } else {
            Log::error('Error checking available payment types: ' . $response->body());
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
}
