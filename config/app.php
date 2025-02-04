<?php

use Illuminate\Support\Facades\Facade;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'FUNHUB'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => 'Asia/Kuala_Lumpur',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Available Locales
    |--------------------------------------------------------------------------
    |
    | This ist the available locales for your application. This array should
    | contain all the locales supported by your application.
    |
    */

    'available_locales' => [
        'en' => 'English',
        'zh' => 'Chinese',
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => 'file',
        // 'store'  => 'redis',
    ],
    /*
        |--------------------------------------------------------------------------
        | News Feed Provider URL
        |--------------------------------------------------------------------------
        |
        | This stores all the news feed url.
        | If there is any changes on the url, we can change it here.
        |
    */
    'news_feed_provider_url' => [
        'https://www.goodymy.com/feed',
        'https://www.goody25.com/feed',
        'https://www.moretify.com/feed'
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => [

        /*
         * Laravel Framework Service Providers...
         */
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,

        /*
         * Package Service Providers...
         */

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        // App\Providers\TelescopeServiceProvider::class,

    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        // 'ExampleClass' => App\Example\ExampleClass::class,
    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Site Settings
    |--------------------------------------------------------------------------
    |
    */

    'rate_limit' => env('RATE_LIMIT', 120),

    'paginate_per_page' => env('PAGINATE_PER_PAGE', 10),
    'max_images_per_article' => env('MAX_IMAGES_PER_ARTICLE', 9),
    'max_size_per_image_kb' => env('MAX_SIZE_PER_IMAGE_KB',  1024 * 1024 * 5),
    'max_size_per_video_kb' => env('MAX_SIZE_PER_VIDEO_KB',  1024 * 1024 * 500),
    'default_user_media_collection' => 'user_uploads',

    'ios_app_store_link' => env('IOS_APP_STORE_LINK', ''),
    'android_play_store_link' => env('ANDROID_PLAY_STORE_LINK', ''),
    'ios_deep_link' => env('IOS_DEEP_LINK', ''),
    'android_deep_link' => env('ANDROID_DEEP_LINK', ''),

    'event_matrix' => [
        'article_created' => 'Article Created',
        'comment_created' => 'Comment Created',
        'comment_created' => 'Comment Created',
        'like_comment' => 'Liked a Comment',
        'like_article' => 'Liked an Article',
        'share_article' => 'Shared an Article',
        'bookmark_an_article' => 'Bookmarked an Article',
        'follow_a_user' => 'Followed a User',
        'accumulated_followers' => 'Accumulated Followers',
        // 'accumulated_article_likes' => 'Accumulated Likes On My Articles',
        // 'accumulated_article_bookmarks' => 'Accumulated Bookmarks On My Articles',
        'completed_profile_setup' => 'Completed profile setup & uploaded avatar',
        'purchased_merchant_offer_cash' => 'Purchase a merchant offer with cash',
        'purchased_merchant_offer_points' => 'Purchase a merchant offer with points',
        'sign_in' => 'Signed in',
        'purchase_gift_card' => 'Purchase a gift card',
        'reviewed_store' => 'Reviewed a store',
        'closed_a_ticket' => 'Closed a complain ticket raised by user',
        'closed_an_information_update_ticket' => 'Closed an information update ticket raised by user',

        'accumulated_likes' => 'Accumulated Likes for an Article',
        'accumulated_bookmarks' => 'Accumulated Bookmarks an Article',
        'accumulated_shares' => 'Accumulated Shares for an Article',
        'accumulated_comments' => 'Accumulated Comments for an Article',
        'accumulated_likes_for_ratings' => 'Accumulated Likes For Ratings for an Store Ratings',
    ],

    'auto_disburse_reward' => env('AUTO_DISBURSE_REWARD', false),
    'recommended_article_cache_hours' => env('RECOMMENDED_ARTICLE_CACHE_HOURS', 2),
    'default_payment_gateway' => env('DEFAULT_PAYMENT_GATEWAY', 'mpay'),

    // merchant offers
    'release_offer_stock_after_min' => env('RELEASE_OFFER_STOCK_AFTER_MIN', 10),

    'recommendation_db_purge_hours' => env('RECOMMENDATION_DB_PURGE', 6),

    'location_default_radius' => env('LOCATION_DEFAULT_RADIUS', 15),

    'recommendation_after_days' => env('RECOMMENDATION_AFTER_DAYS', 3),

    'mpay_default_email_tld' => env('MPAY_DEFAULT_EMAIL', '@funhub.my'),

    'mpay_default_phone' => env('MPAY_DEFAULT_PHONE', '60123456789'),

    'frontend_app' => env('FUNHUB_WEB_URL', 'https://dev-funhub-webapp.funhub.my'),

    'search_location_use_algolia' => env('SEARCH_LOCATION_USE_ALGOLIA', true),

    'missions_spam_threshold' => env('MISSION_SPAM_THRESHOLD', 10), // minutes

    'referral_reward' => env('REFERRAL_REWARD', true),

    'referral_max_hours' => env('REFERRAL_MAX_HOURS', 48),

    'cooldowns' => [
        'following_a_user_notification' => env('COOLDOWN_FOLLOWING_A_USER_NOTIFICATION', 5),
    ],

    'username_changes_days' => env('USERNAME_CHANGES_DAYS', 30),
    'same_merchant_spend_limit' => env('SAME_MERCHANT_SPEND_LIMIT', false),
    'same_merchant_spend_limit_days' => env('SAME_MERCHANT_SPEND_LIMIT_DAYS', 30),

    'recommended_media_partner_article_hide_after_days' => env('RECOMMENDED_MEDIA_PARTNER_ARTICLE_HIDE_AFTER_DAYS', 30),

    'auto_article_categories' => env('AUTO_ARTICLE_CATEGORIES', false),

    'auto_redistribute_vouchers' => env('AUTO_REDISTRIBUTE_VOUCHERS', false),

    'tutorial_steps' => [
        "first_time_visit_recommend_screen",
        "first_time_visit_nearby_stores_screen",
        "first_time_visit_any_store",
        "first_time_visit_review_tab_in_any_store",
        "first_time_visit_mission_tab",
        "first_time_visit_super_deals_tab",
        "first_time_visit_single_deal_screen",
        "second_time_visit_mission_tab",
        "first_time_visit_profile_screen",
        "second_time_visit_profile_screen",
        "first_time_open_profile_drawer",
        "first_time_visit_any_article",
        "first_time_visit_create_screen",
        "first_time_visit_post_screen",
        "first_time_visit_following_article_tab",
        "first_time_see_nearby_store_coachmark",
        "first_time_see_create_article_coachmark",
    ],

    'funbox_ringgit_value' => env('FUNBOX_RINGGIT_VALUE', 5),

    'funhub_web_link' => env('FUNHUB_WEB_LINK', 'https://app.funhub.my/payment/status'),
    'funhub_web_hash_secret' => env('FUNHUB_WEB_HASH_SECRET'),
    'funhub_checkout_secret' => env('FUNHUB_CHECKOUT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | SMS Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration contains settings for SMS service including allowed
    | country codes. You can specify allowed country codes in your .env file
    | as a comma-separated list (e.g., SMS_ALLOWED_COUNTRY_CODES=60,65)
    |
    */
    'sms' => [
        'allowed_country_codes' => array_filter(
            explode(',', env('SMS_ALLOWED_COUNTRY_CODES', '60,65'))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Support Emails
    |--------------------------------------------------------------------------
    |
    | Configure the support email addresses for the application.
    |
    */

    'support_email1' => env('SUPPORT_EMAIL_1', 'admin@dreamax.my'),
    'support_email2' => env('SUPPORT_EMAIL_2', 'content@funhub.my'),
    'tech_support' => env('TECH_SUPPORT', 'tech@funhub.my'),
];
