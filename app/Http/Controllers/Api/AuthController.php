<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Login
     *
     * Login user into the system with email and password and returns user's token used for API authentication
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @bodyParam email string required The email address of the user.  (john@example.com)
     * @bodyParam password string required The password of the user. (min 8 characters)
     * @response scenario=success {
     *  "user": {
     *     id: 1,
     *     name: "John Smith"
     *  },
     *  "token": "AuthenticationTokenHere"
     * }
     * @response status=401 scenario="Invalid Login details" {"message": "Invalid login details"}
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["email": ["The email field is required."], "password": ["The password field is required."] ]}
     */
    public function login(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password')))

            return response()->json([
                'message' => 'Invalid login details',
                401
            ]);

        $user = User::where('email',  $request->email)->firstOrFail();

        # Delete the existing tokens from the database and create a new one
        auth()->user()->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $token
        ]);
    }

    public function loginSocialProvider(Request $request)
    {
    }


    /**
     * Register with Email
     *
     * Register user with email and password
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @bodyParam email string required The email address of the user.  (john@example.com)
     * @bodyParam name string required The name of the user.  (John Smith)
     * @bodyParam password string required The password of the user. (min 8 characters)
     * @response scenario=success {
     *  "user": {
     *     id: 1,
     *     name: "John Smith"
     *  },
     *  "token": "AuthenticationTokenHere"
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["email": ["The email field is required."], "password": ["The password field is required."] ]}
     */
    public function registerWithEmail(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|confirmed'
        ]);

        $user = new User([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password)
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => new UserResource($user),
            'token'  => $token
        ]);
    }

    public function registerSocialProvider(Request $request)
    {
    }


    /**
     * Logout
     *
     * Log User Out and destroy any active tokens of user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
}
