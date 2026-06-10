<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthOtpService
{
    protected Sms $smsService;

    public function __construct(?Sms $smsService = null)
    {
        $this->smsService = $smsService ?? new Sms(
            [
                'url' => config('services.byteplus.sms_url'),
                'username' => config('services.byteplus.sms_account'),
                'password' => config('services.byteplus.sms_password'),
            ],
            [
                'api_url' => config('services.movider.api_url'),
                'key' => config('services.movider.key'),
                'secret' => config('services.movider.secret'),
            ]
        );
    }

    public function normalizePhoneNumber(string $phoneNo): string
    {
        if (str_starts_with($phoneNo, '0')) {
            return substr($phoneNo, 1);
        }

        if (str_starts_with($phoneNo, '60')) {
            return substr($phoneNo, 2);
        }

        if (str_starts_with($phoneNo, '+')) {
            return substr($phoneNo, 1);
        }

        return $phoneNo;
    }

    public function findUserByPhone(string $countryCode, string $phoneNo): ?User
    {
        return User::query()
            ->where('phone_no', $phoneNo)
            ->where('phone_country_code', $countryCode)
            ->first();
    }

    /**
     * Write OTP to users table and send SMS.
     * Uses a direct query update for existing users to avoid Scout/Auditing/model events.
     *
     * @throws QueryException when a new phone number violates a unique constraint
     */
    public function issueAndSend(string $countryCode, string $phoneNo, ?string $name = null): void
    {
        $otp = (string) rand(100000, 999999);
        $expiresAt = now()->addMinutes(1);
        $fullPhoneNo = $countryCode.$phoneNo;

        $user = $this->findUserByPhone($countryCode, $phoneNo);

        if ($user) {
            $this->persistOtpForExistingUser($user->id, $otp, $expiresAt);
        } else {
            $this->createUserWithOtp($countryCode, $phoneNo, $fullPhoneNo, $name, $otp, $expiresAt);
        }

        $this->smsService->sendSms(
            $fullPhoneNo,
            config('app.name').' - Your OTP is '.$otp
        );
    }

    /**
     * @throws QueryException
     */
    public function issueAndSendForUser(User $user): void
    {
        $otp = (string) rand(100000, 999999);
        $expiresAt = now()->addMinutes(1);

        $this->persistOtpForExistingUser($user->id, $otp, $expiresAt);

        $fullPhoneNo = $user->phone_country_code.$user->phone_no;

        $this->smsService->sendSms(
            $fullPhoneNo,
            config('app.name').' - Your OTP is '.$otp
        );
    }

    protected function persistOtpForExistingUser(int $userId, string $otp, $expiresAt): void
    {
        User::query()
            ->whereKey($userId)
            ->update([
                'otp' => $otp,
                'otp_expiry' => $expiresAt,
                'otp_verified_at' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * @throws QueryException
     */
    protected function createUserWithOtp(
        string $countryCode,
        string $phoneNo,
        string $fullPhoneNo,
        ?string $name,
        string $otp,
        $expiresAt
    ): void {
        $now = now();

        DB::table('users')->insert([
            'phone_country_code' => $countryCode,
            'phone_no' => $phoneNo,
            'full_phone_number' => $fullPhoneNo,
            'name' => $name,
            'username' => strtolower(Str::random(6).rand(100, 999)),
            'otp' => $otp,
            'otp_expiry' => $expiresAt,
            'otp_verified_at' => null,
            'status' => User::STATUS_ACTIVE,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
