<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\Api\MerchantOfferCategoryController;
use App\Http\Controllers\Api\UserSettingsController;
use App\Http\Controllers\PaymentController;

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


Route::group(['prefix' => 'v1', 'middleware' => 'setLocale'], function () {
    Route::get('public_user', [\App\Http\Controllers\Api\UserController::class, 'getProfileForPublicView']);
    Route::get('public_store', [\App\Http\Controllers\Api\StoreController::class, 'getPublicStorePublicView']);

    // public routes for articles
    Route::get('public_articles', [\App\Http\Controllers\Api\ArticleController::class, 'getPublicArticles']);
    Route::get('public_articles_single', [\App\Http\Controllers\Api\ArticleController::class, 'getPublicArticleSingle']);
    Route::get('public_articles_single/{article}/offers', [\App\Http\Controllers\Api\ArticleController::class, 'getPublicArticleSingleOffers']);
    Route::get('public_article', [\App\Http\Controllers\Api\ArticleController::class, 'getArticleForPublicView']); // share link

    // public routes for merchant offers
    Route::get('public_offers', [\App\Http\Controllers\Api\MerchantOfferController::class, 'getPublicOffers']);
    Route::get('public_offers_single', [\App\Http\Controllers\Api\MerchantOfferController::class, 'getPublicOferSingle']);
    Route::get('public_offer', [\App\Http\Controllers\Api\MerchantOfferController::class, 'getPublicOfferPublicView']); // share link

    // app to app
    Route::middleware(['application.token'])->group(function() {
        Route::group(['prefix' => 'external'], function () {
            Route::get('locations', [\App\Http\Controllers\Api\LocationController::class, 'index']);
            Route::get('stores', [\App\Http\Controllers\Api\StoreController::class, 'index']);

            //  Kenneth
            Route::get('merchants', [\App\Http\Controllers\Api\SyncMerchantPortalController::class, 'merchants']);
           
            Route::get('merchant/categories', [\App\Http\Controllers\Api\SyncMerchantPortalController::class, 'merchant_categories']);
            // Route::post('campaigns', [\App\Http\Controllers\Api\SyncMerchantPortalController::class, 'campaigns']);
            Route::post('merchant/offer_overview', [\App\Http\Controllers\Api\SyncMerchantPortalController::class, 'offer_overview']);
            Route::post('merchant/offer_lists', [\App\Http\Controllers\Api\SyncMerchantPortalController::class, 'offer_lists']);
            Route::post('merchant/register', [\App\Http\Controllers\Api\SyncMerchantPortalController::class, 'merchant_register']);
            Route::post('merchant/update', [\App\Http\Controllers\Api\SyncMerchantPortalController::class, 'merchant_update']);
            Route::get('merchant/{merchant_id}', [\App\Http\Controllers\Api\SyncMerchantPortalController::class, 'merchant']);
        });
    });

    // primary otp login
    Route::post('check_phone_no', [\App\Http\Controllers\Api\AuthController::class, 'checkPhoneNoExists']); // send otp
    Route::post('sendOtp', [\App\Http\Controllers\Api\AuthController::class, 'sendOtp']); // send otp
    Route::post('verifyOtp', [\App\Http\Controllers\Api\AuthController::class, 'postVerifyOtp']); // verify otp
    Route::post('loginWithPassword', [\App\Http\Controllers\Api\AuthController::class, 'loginWithPassword']); // login with phone no + password
    Route::post('loginWithOtp', [\App\Http\Controllers\Api\AuthController::class, 'loginWithOtp']); // login with phone no + otp
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

        // set email address (used during complete profile), must be authenticated
        Route::post('user/send-email-verification', [\App\Http\Controllers\Api\AuthController::class, 'postSendVerificationEmail']);
        Route::post('user/verify-email', [\App\Http\Controllers\Api\AuthController::class, 'postVerifyEmail']);

        // Import contacts
        Route::post('user/import-contacts', [\App\Http\Controllers\Api\UserContactsController::class, 'postImportContacts']);
        Route::get('user/contacts-friends', [\App\Http\Controllers\Api\UserContactsController::class, 'getContactsNotYetFollowed']);

        // Country & State
        Route::get('countries', [\App\Http\Controllers\Api\CountryController::class, 'getCountries']);
        Route::get('states', [\App\Http\Controllers\Api\StateController::class, 'getStates']);

        // Articles
        Route::get('article_cities', [\App\Http\Controllers\Api\ArticleController::class, 'getArticleCities']);

        // Media
        Route::get('media/signed_upload', [\App\Http\Controllers\Api\MediaController::class, 'getSignedUploadLink']);
        Route::post('media/upload_complete', [\App\Http\Controllers\Api\MediaController::class, 'postUploadMediaComplete']);

        // Post gallery upload
        Route::post('articles/gallery', [\App\Http\Controllers\Api\ArticleController::class, 'postGalleryUpload']);
        Route::post('articles/video-upload', [\App\Http\Controllers\Api\ArticleController::class, 'postVideoUpload']);
        Route::get('articles/my_articles', [\App\Http\Controllers\Api\ArticleController::class, 'getMyArticles']);
        Route::get('articles/my_bookmarks', [\App\Http\Controllers\Api\ArticleController::class, 'getMyBookmarkedArticles']);
        Route::post('articles/report', [\App\Http\Controllers\Api\ArticleController::class, 'postReportArticle']);
        Route::post('articles/not_interested', [\App\Http\Controllers\Api\ArticleController::class, 'postNotInterestedArticle']);
        Route::get('articles/tagged_users', [\App\Http\Controllers\Api\ArticleController::class, 'getTaggedUsersOfArticle']);
        Route::get('articles/merchant_offers/{article}', [\App\Http\Controllers\Api\ArticleController::class, 'getArticleMerchantOffers']);
        Route::post('articles/recommendations', [\App\Http\Controllers\Api\ArticleController::class, 'postSaveArticleRecommendation']);

        Route::get('/articles/nearby', [ArticleController::class, 'getArticlesNearby']);
        Route::get('/articles/keyword', [ArticleController::class, 'getArticlesByKeywordId']);
        Route::get('/articles/search', [ArticleController::class, 'articlesSearch']);

        Route::resource('articles', \App\Http\Controllers\Api\ArticleController::class)->except(['create', 'edit']);
        // Article Tags
        Route::get('article_tags', \App\Http\Controllers\Api\ArticleTagController::class . '@index');
        Route::get('article_tags/all', \App\Http\Controllers\Api\ArticleTagController::class . '@getAllTags');
        Route::get('article_tags/{article_id}', \App\Http\Controllers\Api\ArticleTagController::class . '@getTagByArticleId');

        // Article Categories
        Route::get('article_categories', \App\Http\Controllers\Api\ArticleCategoryController::class . '@index');
        Route::get('article_categories/all', \App\Http\Controllers\Api\ArticleCategoryController::class . '@getAllCategories');
        Route::get('article_categories/{article_id}', \App\Http\Controllers\Api\ArticleCategoryController::class . '@getArticleCategoryByArticleId');

        // Comments
        Route::get('comments/replies/{comment_id}', \App\Http\Controllers\Api\CommentController::class . '@getRepliesByCommentId');
        Route::post('comments/like_toggle', \App\Http\Controllers\Api\CommentController::class . '@postLikeToggle');
        Route::post('comments/report', \App\Http\Controllers\Api\CommentController::class . '@postReportComment');
        Route::get('comments/taggable_users', \App\Http\Controllers\Api\CommentController::class . '@getTaggableUsersInComment');

        Route::resource('comments', \App\Http\Controllers\Api\CommentController::class)->except(['create', 'edit']);

        // Interactions
        Route::get('interactions/users', \App\Http\Controllers\Api\InteractionController::class . '@getUsersOfInteraction');
        Route::resource('interactions', \App\Http\Controllers\Api\InteractionController::class)->except(['create', 'edit', 'update', 'destroy']);
        Route::delete('interactions/{id}', \App\Http\Controllers\Api\InteractionController::class . '@destroy');

        // User Following/Followers
        Route::get('user/followings', [\App\Http\Controllers\Api\UserFollowingController::class, 'getFollowings']);
        Route::get('user/followers', [\App\Http\Controllers\Api\UserFollowingController::class, 'getFollowers']);
        Route::post('user/follow', [\App\Http\Controllers\Api\UserFollowingController::class, 'follow']);
        Route::post('user/unfollow', [\App\Http\Controllers\Api\UserFollowingController::class, 'unfollow']);

        //  Follow requests if profile private
        Route::post('user/request_follow/accept', [\App\Http\Controllers\Api\UserFollowingController::class, 'postAcceptFollowRequest']);
        Route::post('user/request_follow/reject', [\App\Http\Controllers\Api\UserFollowingController::class, 'postRejectFollowRequest']);
        Route::get('user/request_follows', [\App\Http\Controllers\Api\UserFollowingController::class, 'getMyFollowRequests']);

        Route::post('user/report', [\App\Http\Controllers\Api\UserController::class, 'postReportUser']);
        Route::post('user/block', [\App\Http\Controllers\Api\UserController::class, 'postBlockUser']);
        Route::post('user/unblock', [\App\Http\Controllers\Api\UserController::class, 'postUnblockUser']);
        Route::get('user/my_blocked_users', [\App\Http\Controllers\Api\UserController::class, 'getMyBlockedUsers']);
        Route::post('user/delete/request-otp', [\App\Http\Controllers\Api\UserController::class, 'postDeleteAccountRequestOtp']);
        Route::post('user/delete', [\App\Http\Controllers\Api\UserController::class, 'postDeleteAccount']);
        Route::post('user/tutorial-progress', [\App\Http\Controllers\Api\UserController::class, 'postTutorialProgress']);
        Route::post('user/last_known_location', [\App\Http\Controllers\Api\UserController::class, 'postUpdateLastKnownLocation']);

        // Merchant Offers
        Route::prefix('/merchant/offers')->group(function () {
            Route::get('/nearby', [\App\Http\Controllers\Api\MerchantOfferController::class, 'getMerchantOffersNearby']);
            Route::get('/my_bookmarks', [\App\Http\Controllers\Api\MerchantOfferController::class, 'getMyBookmarkedMerchantOffers']);
            Route::get('/', [\App\Http\Controllers\Api\MerchantOfferController::class, 'index']);
            Route::post('/claim', [\App\Http\Controllers\Api\MerchantOfferController::class, 'postClaimOffer']);
            Route::post('/redeem', [\App\Http\Controllers\Api\MerchantOfferController::class, 'postRedeemOffer']);
            Route::post('/cancel', [\App\Http\Controllers\Api\MerchantOfferController::class, 'postCancelTransaction']);
            Route::get('/my_claimed_offers', [\App\Http\Controllers\Api\MerchantOfferController::class, 'getMyMerchantOffers']);
            Route::get('/last_purchase', [\App\Http\Controllers\Api\MerchantOfferController::class, 'getLastPurchaseDateFromMerchantUser']);
            Route::get('/{offer_id}', [\App\Http\Controllers\Api\MerchantOfferController::class, 'show']);

        });

        // Merchants
        Route::prefix('/merchants')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\MerchantController::class, 'index']);
            Route::post('/crm', [\App\Http\Controllers\Api\MerchantController::class, 'postMerchantCrm']);
            Route::get('/rating_categories', [\App\Http\Controllers\Api\MerchantController::class, 'getRatingCategories']);
            Route::get('/nearby', [\App\Http\Controllers\Api\MerchantController::class, 'getNearbyMerchants']);
            Route::get('/{merchant}/locations', [\App\Http\Controllers\Api\MerchantController::class, 'getAllStoresLocationByMerchantId']);
            Route::get('/{merchant}/ratings', [\App\Http\Controllers\Api\MerchantController::class, 'getRatings']);
            Route::post('/{merchant}/ratings', [\App\Http\Controllers\Api\MerchantController::class, 'postRatings']);
            Route::get('/{merchant}/menus', [\App\Http\Controllers\Api\MerchantController::class, 'getMerchantMenus']);
        });

        // Stores
        Route::prefix('/stores')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\StoreController::class, 'index']);
            Route::get('/followings_been_here', [\App\Http\Controllers\Api\StoreController::class, 'getStoresFollowingBeenHere']);
            Route::get('/rating_categories', [\App\Http\Controllers\Api\StoreController::class, 'getRatingCategories']);
            Route::get('/locations', [\App\Http\Controllers\Api\StoreController::class, 'getStoresLocationsByStoreId']);
            Route::get('/stores_by_location', [\App\Http\Controllers\Api\StoreController::class, 'getStoreByLocationId']);
            Route::get('/{store}/ratings', [\App\Http\Controllers\Api\StoreController::class, 'getRatings']);
            Route::post('/{store}/ratings', [\App\Http\Controllers\Api\StoreController::class, 'postRatings']);
            Route::get('/{store}/menus', [\App\Http\Controllers\Api\StoreController::class, 'getMerchantMenus']);
            Route::get('/{store}/ratings/ratings_categories', [\App\Http\Controllers\Api\StoreController::class, 'getStoreRatingCategories']);
        });

        // Merchant Offer Categories
        Route::get('merchant_offer_categories', \App\Http\Controllers\Api\MerchantOfferCategoryController::class . '@index');

        // Merchant Categories
        Route::get('merchant_categories', \App\Http\Controllers\Api\MerchantCategoryController::class . '@index');
        Route::get('merchant_categories/{offer_id}', \App\Http\Controllers\Api\MerchantCategoryController::class . '@getMerchantCategoryByOfferId');

        //Merchant Contacts
        Route::post('merchant-contact', \App\Http\Controllers\Api\MerchantContactController::class . '@postMerchantContact');

        // User Settings
        Route::prefix('/user/settings')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\UserSettingsController::class, 'getSettings']);
            Route::post('/', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSettings']);

            Route::post('/phone_no/otp', [\App\Http\Controllers\Api\UserSettingsController::class, 'postUpdatePhoneNo']);
            Route::post('/phone_no/verify_otp', [\App\Http\Controllers\Api\UserSettingsController::class, 'postUpdatePhoneNoVerifyOtp']);

            Route::post('/email', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveEmail']);
            Route::post('/verify/email', [\App\Http\Controllers\Api\UserSettingsController::class, 'verifyEmail']);
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
            Route::post('/language', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveLanguage']);

            Route::post('/fcm-token', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveFcmToken']);

            // Update password if user is logged in with phone no
            Route::post('/postUpdatePassword', [\App\Http\Controllers\Api\UserSettingsController::class, 'postUpdatePassword']);

            // Update profile privacy
            Route::post('/profile-privacy', [\App\Http\Controllers\Api\UserSettingsController::class, 'postUpdateProfilePrivacy']);

            // Referrals
            Route::get('/referrals/my-code', [\App\Http\Controllers\Api\UserSettingsController::class, 'getMyReferralCode']);
            Route::post('/referrals/save', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveReferral']);

            // Onesignal
            Route::post('/onesignal/save', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveOneSignalSubscriptionId']);
            Route::post('/onesignal/user_id/save', [\App\Http\Controllers\Api\UserSettingsController::class, 'postSaveOneSignalUserId']);

            // Add Payment Cards
            Route::get('/card-tokenization', [UserSettingsController::class, 'cardTokenization'])->name('payment.card-tokenization');
            Route::get('/cards', [UserSettingsController::class, 'getCards'])->name('payment.cards');
            Route::post('/card/remove', [UserSettingsController::class, 'postRemoveCard'])->name('payment.card.remove');
            Route::post('/card/set-as-default', [UserSettingsController::class, 'postSetCardAsDefault'])->name('payment.card.set-as-default');
        });

        // TODO: secure this route
        Route::get('users_by_id', [\App\Http\Controllers\Api\UserController::class, 'getUsersByIds']);
        Route::get('user/{user}', [\App\Http\Controllers\Api\UserController::class, 'show']);

        //user module consolidate api
        Route::get('user', [\App\Http\Controllers\Api\UserController::class, 'getAuthUserDetails']);
        Route::get('public/user/{user}', [\App\Http\Controllers\Api\UserController::class, 'getPublicUser']);
        //single route to update user details(name, username, bio, job_title, dob, gender, location, avatar, cover, category_ids)
        Route::post('user', [\App\Http\Controllers\Api\UserController::class, 'postUpdateUserDetails']);
        Route::post('user/password', [\App\Http\Controllers\Api\UserController::class, 'postUpdatePassword']);
        Route::post('user/email', [\App\Http\Controllers\Api\UserController::class, 'postUpdateEmail']);

        // Views
        Route::prefix('/views')->group(function () {
           Route::post('/', [\App\Http\Controllers\Api\ViewController::class, 'postView']);
           Route::get('/{type}/{id}', [\App\Http\Controllers\Api\ViewController::class, 'getViews']);
        });

        // Products
        Route::prefix('/products')->group(function (){
            Route::get('/', [\App\Http\Controllers\Api\ProductController::class, 'index']);
            Route::post('/checkout', [\App\Http\Controllers\Api\ProductController::class, 'postCheckout']);
            Route::post('/checkout/cancel', [\App\Http\Controllers\Api\ProductController::class, 'postCancelCheckout']);
        });

        // Points & Rewards
        Route::prefix('/points')->group(function () {
            Route::get('/my_balance/all', [\App\Http\Controllers\Api\PointController::class, 'getPointsBalanceByUser']);
            Route::get('/balance', [\App\Http\Controllers\Api\PointController::class, 'getPointBalance']); // Main Reward only
            Route::get('/components/balance', [\App\Http\Controllers\Api\PointController::class, 'getPointComponentBalance']); // Component only
            Route::get('/rewards', [\App\Http\Controllers\Api\PointController::class, 'getRewards']);
            Route::post('/reward_combine', [\App\Http\Controllers\Api\PointController::class, 'postCombinePoints']);

            // ledgers
            Route::get('/ledgers', [\App\Http\Controllers\Api\PointController::class, 'getPointLedger']);
            Route::get('/components/ledgers', [\App\Http\Controllers\Api\PointController::class, 'getPointComponentLedger']);
        });

        // Missions
        Route::prefix('/missions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\MissionController::class, 'index']);
            Route::post('/complete', [\App\Http\Controllers\Api\MissionController::class, 'postCompleteMission']);
            Route::get('/claimables', [\App\Http\Controllers\Api\MissionController::class, 'getClaimableMissions']);
        });

        // Notifications
        Route::prefix('/notifications')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\NotificationController::class, 'getNotifications']);
            Route::post('/mark_as_read', [\App\Http\Controllers\Api\NotificationController::class, 'postMarkSingleUnreadNotificationAsRead']); // single
            Route::post('/mark_all_as_read', [\App\Http\Controllers\Api\NotificationController::class, 'postMarkUnreadNotificationAsRead']); // all unread
        });

        Route::prefix('/locations')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\LocationController::class, 'index']);
            Route::get('/{location}', [\App\Http\Controllers\Api\LocationController::class, 'show']);
        });

        Route::prefix('/transactions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\TransactionController::class, 'index']);
            Route::get('/transaction_no', [\App\Http\Controllers\Api\TransactionController::class, 'getTransactionByNumber']);
            Route::get('/{transaction}', [\App\Http\Controllers\Api\TransactionController::class, 'show']);
        });

        // Help Center
        Route::prefix('/help')->group(function () {
            // Faqs
            Route::get('/faqs', [\App\Http\Controllers\Api\FaqController::class, 'index']);
            Route::get('/faq_categories', [\App\Http\Controllers\Api\FaqController::class, 'getFaqCategories']);

            // support requests
            Route::get('/support_requests', [\App\Http\Controllers\Api\SupportRequestController::class, 'index']);
            Route::post('/support_requests/raise', [\App\Http\Controllers\Api\SupportRequestController::class, 'postRaiseSupportRequest']);
            Route::post('/support_requests/{support_request}/reply', [\App\Http\Controllers\Api\SupportRequestController::class, 'postReplyToSupportRequest']);
            Route::get('/support_requests/{support_request}/messages', [\App\Http\Controllers\Api\SupportRequestController::class, 'getMessagesOfSupportRequest']);
            Route::post('/support_requests/{support_request}/resolve', [\App\Http\Controllers\Api\SupportRequestController::class, 'postResolveSupportRequest']);
            Route::get('/support_requests/categories', [\App\Http\Controllers\Api\SupportRequestController::class, 'getSupportRequestsCategories']);
            Route::post('/support_requests/attach', [\App\Http\Controllers\Api\SupportRequestController::class, 'postAttachmentsUpload']);
        });

        Route::prefix('/campaigns')->group(function () {
            Route::get('/active', [\App\Http\Controllers\Api\CampaignController::class, 'getActiveCampaigns']);
            Route::post('/save/single_aswer', [\App\Http\Controllers\Api\CampaignController::class, 'postSingleAnswer']);
            Route::get('/answers_by_campaign_brand', [\App\Http\Controllers\Api\CampaignController::class, 'getMyAnswersByCampaignAndBrand']);
            Route::get('/questions_by_campaign', [\App\Http\Controllers\Api\CampaignController::class, 'getQuestionsByCampaign']);
            Route::get('/questions_by_brand_campaign', [\App\Http\Controllers\Api\CampaignController::class, 'getCampaignQuestionsByBrand']);
            Route::post('/save/respondant_details', [\App\Http\Controllers\Api\CampaignController::class, 'postCreateCampaignRespondantDetails']);
            Route::get('/respondant_details', [\App\Http\Controllers\Api\CampaignController::class, 'getRespondantDetails']);
        });

        // Maintenance
        Route::get('/maintenance', [MaintenanceController::class, 'getMaintenanceInfo']);

        // Payments
        Route::get('/payment/available_payment_types', [PaymentController::class, 'getAvailablePaymentTypes']);
        Route::get('/payment/funbox_ringgit_value', [PaymentController::class, 'getFunboxRinggitValue']);
    });
});
