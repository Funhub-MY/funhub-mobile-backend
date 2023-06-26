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
    Route::post('login/facebook', [\App\Http\Controllers\Api\AuthController::class, 'facebookLogin']);
    Route::post('login/google', [\App\Http\Controllers\Api\AuthController::class, 'googleLogin']);
    Route::post('login/social', [\App\Http\Controllers\Api\AuthController::class, 'socialLogin']);
    
    // forgot password
    Route::post('reset-password-send-otp', [\App\Http\Controllers\Api\AuthController::class, 'postResetPasswordSendOtp']);
    Route::post('reset-password', [\App\Http\Controllers\Api\AuthController::class, 'postResetPasswordWithOtp']);
    /**
     * Authenticated routes
     */
    Route::group(['middleware' => ['auth:sanctum', 'checkStatus']],  function() {
        Route::post('logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
        Route::post('user/complete-profile', [\App\Http\Controllers\Api\AuthController::class, 'postCompleteProfile']);

        // Country & State
        Route::get('countries', [\App\Http\Controllers\Api\CountryController::class, 'getCountries']);
        Route::get('states', [\App\Http\Controllers\Api\StateController::class, 'getStates']);

        // Articles
        // Post gallery upload
        Route::post('articles/gallery', [\App\Http\Controllers\Api\ArticleController::class, 'postGalleryUpload']);
        Route::post('articles/video-upload', [\App\Http\Controllers\Api\ArticleController::class, 'postVideoUpload']);
        Route::get('articles/my_articles', [\App\Http\Controllers\Api\ArticleController::class, 'getMyArticles']);
        Route::get('articles/my_bookmarks', [\App\Http\Controllers\Api\ArticleController::class, 'getMyBookmarkedArticles']);
        Route::resource('articles', \App\Http\Controllers\Api\ArticleController::class)->except(['create', 'edit']);
        Route::post('articles/report', [\App\Http\Controllers\Api\ArticleController::class, 'postReportArticle']);
        // Article Tags
        Route::get('article_tags', \App\Http\Controllers\Api\ArticleTagController::class . '@index');
        Route::get('article_tags/{article_id}', \App\Http\Controllers\Api\ArticleTagController::class . '@getTagByArticleId');

        // Article Categories
        Route::get('article_categories', \App\Http\Controllers\Api\ArticleCategoryController::class . '@index');
        Route::get('article_categories/all', \App\Http\Controllers\Api\ArticleCategoryController::class . '@getAllCategories');
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
        Route::post('user/report', [\App\Http\Controllers\Api\UserController::class, 'postReportUser']);
        Route::post('user/block', [\App\Http\Controllers\Api\UserController::class, 'postBlockUser']);

        // Merchant Offers
        Route::prefix('/merchant/offers')->group(function () {
            Route::get('/my_bookmarks', [\App\Http\Controllers\Api\MerchantOfferController::class, 'getMyBookmarkedMerchantOffers']);
            Route::get('/', [\App\Http\Controllers\Api\MerchantOfferController::class, 'index']);
            Route::post('/claim', [\App\Http\Controllers\Api\MerchantOfferController::class, 'postClaimOffer']);
            Route::get('/{offer_id}', [\App\Http\Controllers\Api\MerchantOfferController::class, 'show']);
        });

        // Merchant Categories
        Route::get('merchant_categories', \App\Http\Controllers\Api\MerchantCategoryController::class . '@index');
        Route::get('merchant_categories/{offer_id}', \App\Http\Controllers\Api\MerchantCategoryController::class . '@getMerchantCategoryByOfferId');

        // User Settings
        Route::prefix('/user/settings')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\UserSettingsController::class, 'getSettings']);
            Route::post('/', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSettings']);
            Route::post('/email', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveEmail']);
            Route::post('/name', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveName']);
            Route::post('/article_categories', [\App\Http\Controllers\Api\UserSettingsController::class, 'postLinkArticleCategoriesInterests']);
            Route::post('/avatar/upload', [\App\Http\Controllers\Api\UserSettingsController::class, 'postUploadAvatar']);
            // cover upload
            Route::post('/cover/upload', [\App\Http\Controllers\Api\UserSettingsController::class, 'postUploadCover']);
            Route::post('/username', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveUsername']);
            Route::post('/bio', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveBio']);
            Route::post('/dob', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveDob']);
            Route::post('/gender', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveGender']);
            Route::post('/location', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveLocation']);
            Route::post('/job-title', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveJobTitle']);

            Route::post('/fcm-token', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveFcmToken']);

            // Update password if user is logged in with phone no
            Route::post('/postUpdatePassword', [\App\Http\Controllers\Api\UserSettingsController::class, 'postUpdatePassword']);
        });

        // TODO: secure this route
        Route::get('users_by_id', [\App\Http\Controllers\Api\UserController::class, 'getUsersByIds']);
        Route::get('user/{user}', [\App\Http\Controllers\Api\UserController::class, 'show']);

        // Views
        Route::prefix('/views')->group(function () {
           Route::post('/', [\App\Http\Controllers\Api\ViewController::class, 'postView']);
           Route::get('/{type}/{id}', [\App\Http\Controllers\Api\ViewController::class, 'getViews']);
        });

        // Points & Rewards
        Route::prefix('/points')->group(function () {
            Route::get('/my_balance/all', [\App\Http\Controllers\Api\PointController::class, 'getPointsBalanceByUser']);
            Route::get('/balance', [\App\Http\Controllers\Api\PointController::class, 'getPointBalance']); // Main Reward only
            Route::get('/components/balance', [\App\Http\Controllers\Api\PointController::class, 'getPointComponentBalance']); // Component only
            Route::get('/rewards', [\App\Http\Controllers\Api\PointController::class, 'getRewards']);
            Route::post('/reward_combine', [\App\Http\Controllers\Api\PointController::class, 'postCombinePoints']);
        });

        // Missions
        Route::prefix('/missions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\MissionController::class, 'index']);
            Route::post('/complete', [\App\Http\Controllers\Api\MissionController::class, 'postCompleteMission']);
        });

        // Notifications
        Route::prefix('/notifications')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\NotificationController::class, 'getNotifications']);
            Route::post('/mark_all_as_read', [\App\Http\Controllers\Api\NotificationController::class, 'postMarkUnreadNotificationAsRead']);
        });
    });
});
