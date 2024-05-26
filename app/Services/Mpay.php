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
     * @param string $param         Merchant have to add in the delimiter “|” to separate each parameter value. Noted: “|” is “7C” in hexadecimal of ASCII code table.
     * @return void
     */
    public function createTransaction(string $invoice_no, float $amount, string $desc, string $redirectUrl, string $phoneNo = null, string $email = null, $param = null)
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
                'param' => $param,
            ]
        ];

        Log::info('Mpay create transaction data', $data);

        return $data;
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
