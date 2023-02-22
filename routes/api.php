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
    Route::post('login', [\App\Http\Controllers\Api\AuthController::class, 'login']); // login with email
    Route::post('login/{provider}', [\App\Http\Controllers\Api\AuthController::class, 'loginSocialProvider']);
    Route::post('register/email', [\App\Http\Controllers\Api\AuthController::class, 'registerWithEmail']);
    Route::post('register/{provider}', [\App\Http\Controllers\Api\AuthController::class, 'registerSocialProvider']);

    Route::group(['middleware' => ['auth:sanctum']],  function() {
        Route::post('logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
    });

});
