<?php

use Illuminate\Support\Facades\Route;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Laravel\Firebase\Facades\Firebase;
use App\Http\Livewire\MerchantRegister;

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
//Route::get('/firebase', function () {
//    $token = 'eyJhbGciOiJSUzI1NiIsImtpZCI6ImU3OTMwMjdkYWI0YzcwNmQ2ODg0NGI4MDk2ZTBlYzQzMjYyMjIwMDAiLCJ0eXAiOiJKV1QifQ.eyJpc3MiOiJodHRwczovL3NlY3VyZXRva2VuLmdvb2dsZS5jb20vZnVuaHViLTI3YTZmIiwiYXVkIjoiZnVuaHViLTI3YTZmIiwiYXV0aF90aW1lIjoxNjgzMDk5MDgzLCJ1c2VyX2lkIjoidFU0NUdMWmJRUGdDV29pRFB5NG42YUF5cG9sMiIsInN1YiI6InRVNDVHTFpiUVBnQ1dvaURQeTRuNmFBeXBvbDIiLCJpYXQiOjE2ODMwOTkwODMsImV4cCI6MTY4MzEwMjY4MywiZW1haWwiOiJkYW5pZWwud29uZ0BuZWRleC5pbyIsImVtYWlsX3ZlcmlmaWVkIjpmYWxzZSwiZmlyZWJhc2UiOnsiaWRlbnRpdGllcyI6eyJlbWFpbCI6WyJkYW5pZWwud29uZ0BuZWRleC5pbyJdfSwic2lnbl9pbl9wcm92aWRlciI6InBhc3N3b3JkIn19.AxOHu5048nx5vQix3ZngPYIZ8Cd0SNPCfMC3M9Bs6y2hjcuNC8i51yuadoeSjoK3OHBtXFt8hUMUyULDNZVvVMEbLZbP3T6-FmTL36T4ZXrtPtqMy7BifJmARD4to9yphojU5sn4WhYk9O0SPa9Wu29-vBscJDiVE5yojDt-6xnG7lH2OiHgeVNMfYpnOM3nGXBixzdY_REkJYrRa3facJEWQL_rtipSSndxQK_qWU9C3vRAQojDopB8gV3a5zea62Cr2iAQveaWSDAJ6wq87v6IP6df-llt2xLvQxqOxfUebApPtuhnIscM2vAAxK9mHEUE5wK18Kr_-36Mtkb55A';
//    $auth = Firebase::auth();
//    try {
//        $signInResult = $auth->verifyIdToken($token);
//    } catch (FailedToVerifyToken $e) {
//        dd($e->getMessage());
//    }
//    $uid = $signInResult->claims()->get('sub');
//    $user = $auth->getUser($uid);
//    dd($user);
//
//});
Route::get('/firebase/login', function () {
    return view('firebase');
});

Route::get('/firebase/social/login', [\App\Http\Controllers\Api\AuthController::class, 'socialLogin']);
// Shareable link
Route::get('/s/{link}', [\App\Http\Controllers\Api\ShareableLinkController::class, 'load']);

Route::post('/payment/return', [\App\Http\Controllers\PaymentController::class, 'paymentReturn']);
Route::post('/payment/callback', [\App\Http\Controllers\PaymentController::class, 'paymentReturn']);

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

Route::get('/merchants/register', MerchantRegister::class)->name('merchant.register');
