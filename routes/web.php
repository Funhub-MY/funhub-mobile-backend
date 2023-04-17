<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
// Shareable link
Route::get('/s/{link}', [\App\Http\Controllers\Api\ShareableLinkController::class, 'load']);

// any route other than /s redirect to /admin/login
Route::get('/{any}', function () {
    return redirect()->to('/admin/login');
})->where('any', '^((?!s).)*$');

// TODO:: routes below can be deleted once flutter end finish login implementation.
// this is to get google access token, need to access to this page and login.
Route::get('/get/google-access-token', function () {
    return view('welcome');
});
// redirect to google login.
Route::get('/login/google_provider', [\App\Http\Controllers\Api\AuthController::class, 'redirectToGoogle']);
// this call back is use for socialite callback only for unit test. not using at any place.
Route::get('/auth/facebook/callback',function () {
    return redirect()->to('/admin/login');
});
// this call back is use for socialite callback only for unit test. not using at any place.
Route::get('/auth/google/callback', [\App\Http\Controllers\Api\AuthController::class, 'googleCallBack']);

