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
        Route::post('user/complete-profile', [\App\Http\Controllers\Api\AuthController::class, 'postCompleteProfile']);
    
        // Articles
        // Post gallery upload
        Route::post('articles/gallery', [\App\Http\Controllers\Api\ArticleController::class, 'postGalleryUpload']);
        Route::get('articles/my_articles', [\App\Http\Controllers\Api\ArticleController::class, 'getMyArticles']);
        Route::get('articles/my_bookmarks', [\App\Http\Controllers\Api\ArticleController::class, 'getMyBookmarkedArticles']);
        Route::resource('articles', \App\Http\Controllers\Api\ArticleController::class)->except(['create', 'edit']);

        // Article Tags
        Route::get('article_tags', \App\Http\Controllers\Api\ArticleTagController::class . '@index');
        Route::get('article_tags/{article_id}', \App\Http\Controllers\Api\ArticleTagController::class . '@getTagByArticleId');

        // Article Categories
        Route::get('article_categories', \App\Http\Controllers\Api\ArticleCategoryController::class . '@index');
        Route::get('article_categories/{article_id}', \App\Http\Controllers\Api\ArticleCategoryController::class . '@getArticleCategoryByArticleId');

        // Comments
        Route::get('comments/replies/{comment_id}', \App\Http\Controllers\Api\CommentController::class . '@getRepliesByCommentId');
        Route::post('comments/like_toggle', \App\Http\Controllers\Api\CommentController::class . '@postLikeToggle');
        Route::post('comments/report', \App\Http\Controllers\Api\CommentController::class . '@postReportComment');
        Route::resource('comments', \App\Http\Controllers\Api\CommentController::class)->except(['create', 'edit']);
        
        // Interactions
        Route::resource('interactions', \App\Http\Controllers\Api\InteractionController::class)->except(['create', 'edit', 'update']);

        // User Following/Followers
        Route::get('user/followings', [\App\Http\Controllers\Api\UserFollowingController::class, 'getFollowings']);
        Route::get('user/followers', [\App\Http\Controllers\Api\UserFollowingController::class, 'getFollowers']);
        Route::post('user/follow', [\App\Http\Controllers\Api\UserFollowingController::class, 'follow']);
        Route::post('user/unfollow', [\App\Http\Controllers\Api\UserFollowingController::class, 'unfollow']);

        // User Settings
        Route::prefix('/user/settings')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\UserSettingsController::class, 'getSettings']);
            Route::post('/', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSettings']);
            Route::post('/email', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveEmail']);
            Route::post('/name', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveName']);
            Route::post('/article_categories', [\App\Http\Controllers\Api\UserSettingsController::class, 'postLinkArticleCategoriesInterests']);
            Route::post('/avatar/upload', [\App\Http\Controllers\Api\UserSettingsController::class, 'postUploadAvatar']);
            Route::post('/username', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveUsername']);
            Route::post('/bio', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveBio']);
            Route::post('/dob', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveDob']);
            Route::post('/gender', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveGender']);
        });
    });
});
