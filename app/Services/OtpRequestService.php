<?php
namespace App\Services;

use App\Models\OtpRequest;
use Illuminate\Support\Facades\Log;
use App\Services\Sms;

class OtpRequestService
{
    protected $smsService;
    protected $defaultGateway = 'movider';

    public function __construct()
    {
        $this->smsService = new \App\Services\Sms(
            config('services.movider.api_url'),
            config('services.movider.key'),
            config('services.movider.secret')
        );
    }

    /**
     * Send OTP to user phone no
     *
     * @param integer $user_id
     * @param string $phone_no_country_code
     * @param string $phone_no
     * @return boolean
     */
    public function sendOtp($user_id, $phone_no_country_code, $phone_no, $activity = null)
    {
        // check phone no has prefix 0 remove it first
        if (substr($phone_no, 0, 1) == '0') {
            $phone_no = substr($phone_no, 1);
        } else if (substr($phone_no, 0, 2) == '60') {
            $phone_no = substr($phone_no, 2);
        }

        // check phone no has prefix + remove it first
        if (substr($phone_no, 0, 1) == '+') {
            $phone_no = substr($phone_no, 1);
        }

        // check if user has current otp requests not expired yet
        $otpRequest = OtpRequest::where('user_id', $user_id)
            ->where('verified_at', null)
            ->where('expires_at', '>', now())
            ->first();

        // check if user exists first
        if (!$otpRequest) {
            Log::info('[OtpRequestService] User not found', ['user_id' => $user_id]);
            throw new \Exception('User not found, provide correct ID');
            return false;
        }

        if ($otpRequest) {
            // just resend the same otp
            $this->smsService->sendSms($phone_no_country_code . $phone_no, 'Your OTP is '.$otpRequest->otp);
            Log::info('[OtpRequestService] Resend OTP', ['user_id' => $user_id, 'otp' => $otpRequest->otp]);
        } else {
            // no new otp yet, create one
            $otp = rand(1000, 9999);

            OtpRequest::create([
                'user_id' => $user_id,
                'otp' => $otp,
                'expires_at' => now()->addMinutes(1),
                'verified_at' => null,
                'gateway' => $this->defaultGateway,
                'activity' => $activity ?? null
            ]);

            // send
            $this->smsService->sendSms($phone_no_country_code . $phone_no, 'Your OTP is '.$otp);
            Log::info('[OtpRequestService] Send OTP', ['user_id' => $user_id, 'otp' => $otp]);
        }

        return true;
    }

    /**
     * Verify OTP
     *
     * @param integer $user_id
     * @param string $otp
     * @return boolean
     */
    public function verifyOtp($user_id, $otp)
    {
        $otpRequest = OtpRequest::where('user_id', $user_id)
            ->where('otp', $otp)
            ->where('verified_at', null)
            ->where('expires_at', '>', now())
            ->first();

        if ($otpRequest) {
            $otpRequest->update(['verified_at' => now()]);
            return true;
        }

        return false;
    }
}
