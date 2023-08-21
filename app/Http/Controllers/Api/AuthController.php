<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FirebaseAuthController;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\AuthException;
use Kreait\Firebase\Exception\FirebaseException;
use Laravel\Socialite\Facades\Socialite;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Laravel\Firebase\Facades\Firebase;

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
     * @group Authentication
     * @unauthenticated
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

        // check phone no has prefix 0 remove it first
        if (substr($request->phone_no, 0, 1) == '0') {
            $request->merge(['phone_no' => substr($request->phone_no, 1)]);
        } else if (substr($request->phone_no, 0, 2) == '60') {
            $request->merge(['phone_no' => substr($request->phone_no, 2)]);
        }

        // check phone no has prefix + remove it first
        if (substr($request->phone_no, 0, 1) == '+') {
            $request->merge(['phone_no' => substr($request->phone_no, 1)]);
        }

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
     * @group Authentication
     * @unauthenticated
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

        // check if start with 0 or 60 for phone_no, remove it first
        if (substr($request->phone_no, 0, 1) == '0') {
            $request->merge(['phone_no' => substr($request->phone_no, 1)]);
        } else if (substr($request->phone_no, 0, 2) == '60') {
            $request->merge(['phone_no' => substr($request->phone_no, 2)]);
        }

        // get user
        $user = User::where('phone_no', $request->phone_no)
            ->where('phone_country_code', $request->country_code)
            ->first();

        // Generate new OTP
        $otp = rand(100000, 999999);
        if (!$user) {
            // user doest not exist
            // register user account first with phone no.
            try {
                $user = User::create([
                    'phone_country_code' => $request->country_code,
                    'phone_no' => $request->phone_no, // unique
                    'otp' => $otp,
                    'otp_expiry' => now()->addMinutes(1),
                    'otp_verified_at' => null,
                ]);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Phone Number already registered'], 422);
            }
        } else {
             $user->update([
                'otp' => $otp,
                'otp_expiry' => now()->addMinutes(1),
                'otp_verified_at' => null,
            ]);
        }

        // Fires SMS
        if ($user) {
            try {
                $this->smsService->sendSms($user->full_phone_no, config('app.name')." - Your OTP is ".$user->otp);
            } catch (\Exception $e) {
                Log::error($e->getMessage(), [
                    'phone_no' => $user->full_phone_no,
                    'otp' => $user->otp,
                ]);
                return response()->json(['message' => 'Failed to send OTP'], 422);
            }
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
     * @group Authentication
     * @unauthenticated
     *
     * @bodyParam country_code string required The country code of user's phone number. Example: 60
     * @bodyParam phone_no string required The Phone No of the user. Example: 1234567890
     * @bodyParam otp string required The OTP sent to user's phone number. Example: 123456
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

        // check phone no has prefix 0 remove it first
        if (substr($request->phone_no, 0, 1) == '0') {
            $request->merge(['phone_no' => substr($request->phone_no, 1)]);
        } else if (substr($request->phone_no, 0, 2) == '60') {
            $request->merge(['phone_no' => substr($request->phone_no, 2)]);
        }

        // check phone no has prefix + remove it first
        if (substr($request->phone_no, 0, 1) == '+') {
            $request->merge(['phone_no' => substr($request->phone_no, 1)]);
        }

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
     * @group Authentication
     * @unauthenticated
     *
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
     * Complete Profile
     *
     * Complete user profile after registration
     * @param Request $request
     *
     * @group Authentication
     * @authenticated
     * @bodyParam name string required The name of the use. Example: John Smith
     * @bodyParam email string required The email of the user. Example: john@example.com
     * @bodyParam password string The password of the user(social login do not need to provide). Example: abcd1234
     *
     * @response scenario=success {"message" : "Profile Updated"}
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["name": ["The Name field is required."], "email": ["The Email field is required."] ]}
     * @response status=422 scenario="Invalid Form Fields" {"message": "Please verify your phone number first" ]}
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     */
    public function postCompleteProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'email|unique:users,email,' . auth()->user()->id,
        ]);

        // auth user if social login (has google_id or facebook_id) then no need to verify password required rule
        if (!(auth()->user()->google_id || auth()->user()->facebook_id)) {
            $request->validate([
                'password' => 'required|string|min:8',
            ]);

            // ensure user is otp verified
            if (!auth()->user()->otp_verified_at) {
                return response()->json(['message' => 'Please verify your phone number first'], 422);
            }
        }

        $user = auth()->user();
        $user->update([
            'name' => $request->name,
            'email' => $request->email ?? null,
            'password' => ($request->password) ? Hash::make($request->password) : null,
        ]);

        return response()->json(['message' => 'Profile Updated'], 200);
    }

    /**
     * Logout
     *
     * Log User Out and destroy any active tokens of user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Authentication
     * @authenticated
     *
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

    /**
     * Login with Facebook
     *
     * Login user with Facebook
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Authentication
     * @unauthenticated
     *
     * @bodyParam access_token string required The access token of the user from Facebook. Example: 1234567890
     */
    public function facebookLogin(Request $request)
    {
        // get access token from
        $token = $request->input('access_token');

        $socialiteUser = Socialite::driver('facebook')->userFromToken($token);

        //check if the user already exists in the database
        $user = User::where('email', $socialiteUser->getEmail())->first();

        if(!$user) {
            //if user does not exist in the database, create a new user using the Facebook data
            $user = new User();
            $user->name = $socialiteUser->getName();
            $user->email = $socialiteUser->getEmail();
            $user->facebook_id = $socialiteUser->getId();
            $user->save();
        }

        //log the new user in
        auth()->login($user);
        $sanctumToken = $user->createToken('authToken');

        return response()->json([
            'status' => 'success',
            'message' => 'Logged in successfully',
            'user' => new UserResource($user),
            'token' => $sanctumToken
        ], 200);
    }

    /**
     * Login with Google
     *
     * Login user with Google
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Authentication
     * @unauthenticated
     * @bodyParam access_token string required The access token of the user from Google. Example: 1234567890
     */
    public function googleLogin(Request $request) {
        // get access token from
        $token = $request->input('access_token');

        $socialiteUser = Socialite::driver('google')->userFromToken($token);

        //check if the user already exists in the database
        $user = User::where('email', $socialiteUser->getEmail())->first();

        if(!$user) {
            //if user does not exist in the database, create a new user using the Facebook data
            $user = new User();
            $user->name = $socialiteUser->getName();
            $user->email = $socialiteUser->getEmail();
            $user->google_id = $socialiteUser->getId();
            $user->save();
        }

        //log the new user in
        auth()->login($user);
        $sanctumToken = $user->createToken('authToken');

        return response()->json([
            'status' => 'success',
            'message' => 'Logged in successfully',
            'user' => new UserResource($user),
            'token' => $sanctumToken
        ], 200);
    }
    /**
     * Login with Social (via Firebase Auth)
     *
     * Login user with Social
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Authentication
     * @unauthenticated
     * @bodyParam access_token string required Firebase Auth Token (). Example: 'ey271236...'
     * @response scenario=success {
     *  "user": {
     *     id: 1,
     *     name: "John Smith"
     *  },
     *  "token": "AuthenticationTokenHere"
     * }
     * @response status=422 scenario="Invalid Token" {"message": "Invalid Token" ]}
     */
    public function socialLogin(Request $request) {
        // get access token from
        $token = $request->input('access_token');
        $firebase_auth = Firebase::auth();
        // get verify ID, wrap it in a try catch.

        try {
            $verified_id = $firebase_auth->verifyIdToken($token);
        } catch (FailedToVerifyToken $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'Invalid Token'], 422);
        }

        // as this point, the auth should be verified at firebase.
        $uid = $verified_id->claims()->get('sub');
        if ($uid) {
            $firebase_user = $firebase_auth->getUser($uid);
        } else {
            return response()->json(['message' => 'Invalid Token'], 422);
        }

        $socialid = null;
        if ($firebase_user->providerData[0]->providerId == 'google.com' || $firebase_user->providerData[0]->providerId == 'apple.com') {
            $socialid = $firebase_user->providerData[0]->uid;
            Log::info('socialid via provider data: ' . $socialid);
        } else {
            // need to get facebook_id.
            $socialid = $firebase_user->uid; // use uid at the moment.
            Log::info('socialid via uid: ' . $socialid);
        }

        //check if the user already exists in the database
        $user = User::where('google_id', $socialid)
            ->orWhere('facebook_id', $socialid)
            ->orWhere('apple_id', $socialid)
            ->first();

        if(!$user) {
            //if user does not exist in the database, create a new user using the Facebook data
            $user = new User();

            if ($firebase_user->displayName == null || $firebase_user->displayName == '') {
                $pattern = '/(.*)@.*$/';
                preg_match($pattern, $firebase_user->email, $matches);
                $textBeforeDomain = trim($matches[1]);
                $cleanedText = preg_replace('/[^a-zA-Z0-9]+/', '', $textBeforeDomain);
                $user->name = $cleanedText;
            } else {
                $user->name = $firebase_user->displayName;
            }

            $user->email = $firebase_user->email;

            // Save IDs to associated fields in DB for social providers
            if ($firebase_user->providerData[0]->providerId == 'google.com') { // Google Login
                $user->google_id = $firebase_user->providerData[0]->uid;
            } else if ($firebase_user->providerData[0]->providerId == 'facebook.com'){ // Facebook Login
                // need to get facebook_id.
                $user->facebook_id = $firebase_user->uid; // use uid at the moment.
            } else if ($firebase_user->providerData[0]->providerId == 'apple.com') { // Apple Login
                // password login
                try {
                    $user->apple_id = $firebase_user->providerData[0]->uid;
                } catch (\Exception $e) {
                    Log::error($e->getMessage(), [
                        'providerData' => $firebase_user->providerData
                    ]);
                }
                $user->apple_id = $firebase_user->uid; // use uid at the moment.
            }
            $user->save();
        }

        //log the new user in
        auth()->login($user);
        $sanctumToken = $user->createToken('authToken');

        return response()->json([
            'status' => 'success',
            'message' => 'Logged in successfully',
            'user' => new UserResource($user),
            'token' => $sanctumToken
        ], 200);

    }
    // TODO:: functions below can be deleted once flutter end finish login implementation. It was created for unit testing purpose.
    public function redirectToGoogle()
    {
        // redirect user to login as google.
        return Socialite::driver('google')->redirect();
    }

    public function googleCallBack()
    {
        $user = Socialite::driver('google')->user();
    }

    /**
     * Reset Password Send NEW OTP (Step 1)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Authentication
     * @unauthenticated
     *
     * @bodyParam phone_no string required The phone no of the user. Example: 0123456789
     * @response scenario=success {
     * "status": "success",
     * "message": "OTP sent successfully"
     * }
     *
     */
    public function postResetPasswordSendOtp(Request $request) {
        $this->validate($request, [
            'phone_no' => 'required'
        ]);
        // remove left 0 from phone_no if there is
        $phone_no = ltrim($request->input('phone_no'), '0');
        // get user by phone no
        $user = User::where('phone_no', $phone_no)->first();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        } else {
             // reject if user is logged in using google/facebook
             if ($user->google_id || $user->facebook_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unable to reset password for google/facebook account'
                ], 400);
            }

            // create a new otp
            $otp = rand(100000, 999999);
            $user->update([
                'otp' => $otp,
                'otp_expiry' => now()->addMinutes(1),
            ]);

            // send otp to user
            $this->smsService->sendSms($user->full_phone_no, config('app.name')." - Your OTP is ".$user->otp);
            return response()->json([
                'status' => 'success',
                'message' => 'OTP sent successfully'
            ], 200);
        }
    }

    /**
     * Reset Password with OTP (Step 2)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Authentication
     * @unauthenticated
     *
     * @bodyParam phone_no string required The phone no of the user. Example: 0123456789
     * @bodyParam new_password string required The new password of the user. Example: 123456
     * @bodyParam otp string required The otp of the user. Example: 123456
     * @response scenario=success {
     * "status": "success",
     * "message": "Password reset successfully"
     * }
     */
    public function postResetPasswordWithOtp(Request $request) {
        $this->validate($request, [
            'phone_no' => 'required',
            'new_password' => 'required',
            'otp' => 'required',
        ]);

        // find user by phone number
        $phone_no = ltrim($request->input('phone_no'), '0');
        // get user by phone no
        $user = User::where('phone_no', $phone_no)
            ->where('otp', $request->input('otp'))
            ->first();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        } else {
            // reject if user is logged in using google/facebook
            if ($user->google_id || $user->facebook_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unable to reset password for google/facebook account'
                ], 400);
            }
            // user is found, check if otp is expired
            if ($user->otp_expiry < now()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'OTP expired, please request again.'
                ], 400);
            } else {
                // otp is not expired, update user password
                $user->update([
                    'password' => Hash::make($request->input('new_password')),
                    'otp' => null,
                    'otp_expiry' => null,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Password updated successfully'
                ], 200);
            }
        }
    }
}
