<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::group(['prefix' => 'v1'], function () {
    // primary otp login
    Route::post('sendOtp', [\App\Http\Controllers\Api\AuthController::class, 'sendOtp']); // send otp
    Route::post('verifyOtp', [\App\Http\Controllers\Api\AuthController::class, 'postVerifyOtp']); // verify otp
    Route::post('loginWithPassword', [\App\Http\Controllers\Api\AuthController::class, 'loginWithPassword']); // login with phone no + password
    Route::post('register/otp', [\App\Http\Controllers\Api\AuthController::class, 'registerWithOtp']);

    // social provider logins
    // Route::post('login/{provider}', [\App\Http\Controllers\Api\AuthController::class, 'loginSocialProvider']);
    // Route::post('register/{provider}', [\App\Http\Controllers\Api\AuthController::class, 'registerSocialProvider']);


    Route::group(['middleware' => ['auth:sanctum']],  function() {
        Route::post('logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
    
        // Articles
        Route::post('articles/images', \App\Http\Controllers\Api\ArticleController::class . '@postGalleryUploads');
        Route::resource('articles', \App\Http\Controllers\Api\ArticleController::class)->except(['create', 'edit']);

        // Comments
        Route::resource('comments', \App\Http\Controllers\Api\CommentController::class)->except(['create', 'edit']);
        
        // Interactions
        Route::resource('interactions', \App\Http\Controllers\Api\InteractionController::class)->except(['create', 'edit', 'update']);

        // User Settings
        Route::get('user/settings', [\App\Http\Controllers\Api\UserSettingsController::class, 'getSettings']);
        Route::post('user/settings', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSettings']);
    });
});
