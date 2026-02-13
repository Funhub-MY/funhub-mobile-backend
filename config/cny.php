<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CNY Lucky Draw Campaign Window
    |--------------------------------------------------------------------------
    | Draw chances are only given for product purchases within this period
    | (in app timezone). Purchases outside this window do not get lucky draw.
    */
    'lucky_draw_campaign_start' => env('CNY_LUCKY_DRAW_CAMPAIGN_START', '2026-02-14 00:00:00'),
    'lucky_draw_campaign_end'   => env('CNY_LUCKY_DRAW_CAMPAIGN_END', '2026-03-08 11:59:59'),

];
