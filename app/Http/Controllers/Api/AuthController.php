<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $smsService;
    public function __construct()
    {
        $this->smsService = new \App\Services\Sms(
            config('services.movider.api_url'),
            config('services.movider.key'),
            config('services.movider.secret')
        );
    }

    /**
     * Login with Password
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @bodyParam country_code string required Country code of phone number. Example: 60
     * @bodyParam phone_no string required Phone number. Example: 1234567890
     * @bodyParam password string required Password. Example: abcd1234
     * @response scenario=success {
     *  "user": {},
     *  "token": "auth_token"
     * }
     * @response status=401 scenario="Invalid Credentials" {"message": "Invalid Credentials"}
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["country_code": ["The Country COde field is required."], "phone_no": ["The Phone No field is required."] ]}
     */
    public function loginWithPassword(Request $request): JsonResponse
    {
        $request->validate([
            'country_code' => 'required|string',
            'phone_no' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('phone_no', $request->phone_no)
            ->where('phone_country_code', $request->country_code)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid Credentials'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], 200);
    }

    /**
     * Send OTP
     *
     * Send SMS OTP to phone number
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @bodyParam country_code string required The country code of user's phone number. Example: 60
     * @bodyParam phone_no string required The Phone No of the user. Example: 1234567890
     * @response scenario=success {
     *  "message": "OTP Sent!"
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["country_code": ["The Country COde field is required."], "phone_no": ["The Phone No field is required."] ]}
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'country_code' => 'required|string',
            'phone_no' => 'required|string',
        ]);

        $user = User::where('phone_no', $request->phone_no)
            ->where('phone_country_code', $request->country_code)
            ->first();

        $success = false;
        if ($user) {
            // fire sms
            if ($user->otp && $user->otp_expiry < now()) { // existing otp still valid
                $success = $this->smsService->sendSms($user->full_phone_no, config('app.name')." - Your OTP is ".$user->otp);
            } else {
                $otp = rand(100000, 999999);
                $user->update([
                    'otp' => $otp,
                    'otp_expiry' => now()->addMinutes(1), 
                ]);
                $success = $this->smsService->sendSms($user->full_phone_no, config('app.name')." - Your OTP is ".$user->otp);
            }
        } else {
            // user doest not exist
            // register user account first with phone no.
            $otp = rand(100000, 999999);
            try {
                $user = User::create([
                    'phone_country_code' => $request->country_code,
                    'phone_no' => $request->phone_no, // unique
                    'otp' => $otp,
                    'otp_expiry' => now()->addMinutes(1), 
                ]);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Phone Number already registered'], 422);
            }
            // fire sms
            $success = $this->smsService->sendSms($user->full_phone_no, config('app.name')." - Your OTP is ".$user->otp);
        }
        
        return response()->json(['message' => 'OTP sent'], 200);
    }

    /**
     * Verify OTP
     *
     * Login user into the system with OTP
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @bodyParam country_code string required The country code of user's phone number. Example: 60
     * @bodyParam phone_no string required The Phone No of the user. Example: 1234567890
     * @bodyParam otp string required The OTP sent to user's phone number. Example: 123456
     * 
     * @response scenario=success {
     *  "user": {
     *     id: 1,
     *     name: "John Smith"
     *  },
     *  "token": "AuthenticationTokenHere"
     * }
     * @response status=401 scenario="Invalid Login details" {"message": "Invalid login details"}
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["country_code": ["The Country COde field is required."], "phone_no": ["The Phone No field is required."] ]}
     */
    public function postVerifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'country_code' => 'required|string',
            'phone_no' => 'required|string',
            'otp' => 'required|string',
        ]);

        $user = User::where('phone_no', $request->phone_no)
            ->where('phone_country_code', $request->country_code)
            ->where('otp', $request->otp)
            ->first();

        if ($user) {
            // user exists in system
            // update otp to null
            $user->update([
                'otp' => null,
                'otp_expiry' => null,
                'otp_verified_at' => now(),
            ]);
             // log user in
            $token = $user->createToken('authToken');
            Auth::login($user);

            return response()->json([
                'user' => new UserResource($user),
                'token' => $token->plainTextToken,
            ], 200);
        }

        return response()->json(['message' => 'OTP Invalid or Expired'], 422);
    }

    /**
     * Register with OTP
     * 
     * Register user with OTP
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @header Content-Type application/x-www-form-urlencoded
     * @bodyParam country_code string required The country code of user's phone number. Example: 60
     * @bodyParam phone_no string required The Phone No of the user. Example: 1234567890
     * @bodyParam otp string required The OTP sent to user's phone number. Example: 123456
     * @bodyParam name string required The name of the use. Example: John Smith
     * @bodyParam password string required The password of the user. Example: abcd1234
     * 
      * @response scenario=success {
     *  "user": {
     *     id: 1,
     *     name: "John Smith"
     *  },
     *  "token": "AuthenticationTokenHere"
     * }
     * 
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["country_code": ["The Country COde field is required."], "phone_no": ["The Phone No field is required."] ]}
     */
    public function registerWithOtp(Request $request)
    {
        $request->validate([
            'country_code' => 'required|string',
            'phone_no' => 'required|string',
            'otp' => 'required|string',
            'name' => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        // check if matching user and otp exists
        $user = User::where('phone_no', $request->phone_no)
            ->where('phone_country_code', $request->country_code)
            ->where('otp', $request->otp)
            ->first();

        if ($user) {
            // update user remaining profile
            // user is registered during first time send sms otp (see sendOtp)
            $user->update([
                'otp' => null,
                'otp_expiry' => null,
                'name' => $request->name,
                'email' => $request->has('email') ? $request->email : null,
                'password' => Hash::make($request->password),
            ]);

            // login user and issue token
             // log user in
             $token = $user->createToken('authToken');
             Auth::login($user);
 
             return response()->json([
                 'user' => new UserResource($user),
                 'token' => $token->plainTextToken,
             ], 200);
        } else {
            return response()->json(['message' => 'OTP Invalid or Expired'], 422);
        }
    }

    /**
     * Logout
     *
     * Log User Out and destroy any active tokens of user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @header Content-Type application/x-www-form-urlencoded
     * @response scenario=success {"message" : "Logged Out"}
     * @response status=401 scenario="Access Denied" {"message": "Access Denied"}
     */
    public function logout()
    {
        auth()->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out'
        ]);
    }
}
