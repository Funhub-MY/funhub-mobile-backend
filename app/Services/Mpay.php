<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Expr\Cast\Double;

class Mpay {
    protected $url, $mid, $hashKey;
    protected $secureHash;

    public function __construct($mid, $hashKey, $uat = true)
    {
        if (!$uat && !config('app.debug')) {
            $this->url = config('services.mpay.prod_url');
        } else {
            // uat mode
            $this->url = config('services.mpay.uat_url');
        }
        $this->mid = $mid;
        $this->hashKey = $hashKey;
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
     * @param string $param         Merchant have to add in the delimiter “|” to separate each parameter value. Noted: “|” is “7C” in hexadecimal of ASCII code table.
     * @return void
     */
    public function createTransaction(string $invoice_no, float $amount, string $desc, string $redirectUrl, string $phoneNo, string $email, $param = null)
    {
        // check if mid and hashKey is set
        if (!$this->mid || !$this->hashKey) {
            throw new \Exception('Mpay MID or hash key is not set');
        }

        // convert amount 000000000100 represent RM 1.00
        // 000000000100 = RM 1.00 000000001000 = RM 10.00 000000010000 = RM 100.00
        $amount = str_pad(number_format($amount, 2, '', ''), 12, '0', STR_PAD_LEFT);

        $data = [
            'secureHash' => $this->generateHashForRequest($this->mid, $invoice_no, $amount),
            'mid' => $this->mid,
            'invno' => $invoice_no,
            'capture_amt' => $amount,
            'desc' => $desc,
            'postURL' => $redirectUrl,
            'phone' => $phoneNo,
            'email' => $email,
            'param' => $param,
            'authorize' => 'authorize'
        ];

        Log::info('Mpay create transaction', [
            'url' => $this->url .'api/mpgs/capture',
            'request' => $data
        ]);

        $response = Http::post($this->url .'api/mpgs/capture', $data);

        if ($response->ok() || $response->status() === 200) {
            // convert return json to array
            return json_decode($response, true);
        } else {
            throw new \Exception('Failed to create transaction');
        }
    }

    /**
     * Generate hash for request
     *
     * @param string $mid
     * @param string $invoice_no
     * @param float $amount
     * @return string
     */
    private function generateHashForRequest($mid, $invoice_no, $amount)
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
    private function generateHashForResponse($mid, $responseCode, $authCode, $invoice_no, $amount)
    {
        // append hashkey,mid,responseocde,authcode,invoice_no,amount into a string and call gensecurehash
        $string = $this->hashKey . $mid . $responseCode . $authCode . $invoice_no . strval($amount);
        return $this->secureHash->generateSecureHash($string);
    }
}