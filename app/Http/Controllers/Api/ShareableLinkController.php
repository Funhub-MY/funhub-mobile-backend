<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShareableLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShareableLinkController extends Controller
{
    /**
     * Load link then determine user-agent only create deep link to push to app
     */
    public function load(Request $request)
    {
        $link = $request->link;
        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        if ($link == null || $userAgent == null) {
            Log::info('Link or user-agent is null', [
                'link' => $link,
                'user-agent' => $userAgent
            ]);
            return abort(404);
        }

        // check if link exists
        $shareableLink = ShareableLink::where('link', $link)->first();
        if ($shareableLink == null) {
            Log::info('Shareable link is null', [
                'shareable_link' => $shareableLink,
            ]);
            return abort(404);
        }

        // get link structure
        $linkStructure = $this->generateLinkStructure($shareableLink);

        // check if user-agent is mobile
        if (preg_match('/(android|iphone|ipad|mobile)/i', $userAgent)) {
            // detect if ios redirect to app store, else redirec to play store
            if (preg_match('/(ios)/i', $userAgent)) {
                // eg. flutter://flutter.dev?article_id=1
                Log::info('Redirecting to ios deep link', [
                    'link' => config('app.ios_deep_link').'?'.$linkStructure,
                ]);
                return redirect(config('app.ios_deep_link').'?'.$linkStructure);
            } else {
                // eg. flutter://flutter.dev?article_id=1
                Log::info('Redirecting to android deep link', [
                    'link' => config('app.android_deep_link').'?'.$linkStructure,
                ]);
                return redirect(config('app.android_deep_link').'?'.$linkStructure);
            }
        } else {
            // detect if ios redirect to app store, else redirec to play store
            if (preg_match('/(ios)/i', $userAgent)) {
                Log::info('Redirecting to ios app store', [
                    'link' => config('app.ios_app_store_link'),
                ]);
                return redirect(config('app.ios_app_store_link'));
            } else {
                Log::info('Redirecting to android play store', [
                    'link' => config('app.android_play_store_link'),
                ]);
                return redirect(config('app.android_play_store_link'));
            }
        }

        return abort(404);
    }

    /**
     * Generate Link Param structure based on model_id and model_type
     */
    private function generateLinkStructure(ShareableLink $link)
    {
        // convert model_type App\Models\Article to article_id
        $modelIdStructure = strtolower(str_replace('App\Models\\', '', $link->model_type)).'_id';

        // eg. article
        return $modelIdStructure . '=' . $link->model_id;
    }
}
