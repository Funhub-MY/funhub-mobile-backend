<?php
namespace App\Services;

use App\Models\User;
use DateTime;
use PHPUnit\Framework\TestCase;
use GuzzleHttp;
use GuzzleHttp\Client;

class OneSignalService
{
    protected $client;
    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.onesignal.com/apps/' . config('services.onesignal.app_id') . '/',
            'headers' => [
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ],
        ]);
    }

    public function syncUser(User $user)
    {
        $user->loadCount(['followings', 'followers', 'articles']);

        $data = [
            'identity' => [
                'external_id' => (string) $user->id,
            ],
            'properties' => [
                'language' => $user->last_lang ?? 'en',
                'tags' => [
                    'name' => $user->name,
                    'username' => $user->username,
                    'verified_email' => $user->verified_email,
                    'phone_country_code' => $user->phone_country_code,
                    'phone_no' => $user->phone_no,
                    'articles_count' => (string) $user->articles_count,
                    'following_count' => (string) $user->following_count,
                    'followers_count' => (string) $user->followers_count,
                    'has_completed_profile' => (string) $user->has_completed_profile,
                    'has_avatar' => (string) $user->has_avatar,
                    'point_balance' => (string) $user->point_balance,
                    'is_profile_private' => $user->is_profile_private,
                    'dob' => $user->dob,
                    'gender' => $user->gender,
                    'job_title' => $user->job_title,
                    'country' => $user->country->name,
                    'state' => $user->state->name,
                    'interested_categories' => implode(',', $user->articleCategoriesInterests->pluck('name')->toArray()),
                    'created_at' => $user->created_at->toDateTimeString(),
                ],
            ],
        ];

        if ($user->onesignal_subscription_id) {
            $data['subscriptions'] = [
                [
                    'id' => $user->subscription_id,
                ],
            ];
        }

        $response = $this->client->post('users', [
            'json' => $data,
        ]);

        $responseData = json_decode($response->getBody(), true);

        if ($response->getStatusCode() === 200 && isset($responseData['id'])) {
            $user->onesignal_subscription_id = $responseData['id'];
            $user->save();
        }
    }

    public function bulkSyncUsers($users)
    {
        foreach ($users as $user) {
            $this->syncUser($user);
        }
    }
}
