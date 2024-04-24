<?php
namespace App\Services;

use DateTime;
use onesignal\client\api\DefaultApi;
use onesignal\client\Configuration;
use onesignal\client\model\GetNotificationRequestBody;
use onesignal\client\model\Notification;
use onesignal\client\model\StringMap;
use onesignal\client\model\Player;
use onesignal\client\model\UpdatePlayerTagsRequestBody;
use onesignal\client\model\ExportPlayersRequestBody;
use onesignal\client\model\Segment;
use onesignal\client\model\FilterExpressions;
use PHPUnit\Framework\TestCase;
use GuzzleHttp;

class OneSignalService
{
    protected $client;
    public function __construct()
    {
        $config = Configuration::getDefaultConfiguration()
            ->setAppKeyToken(config('services.onesignal.app_id'))
            ->setUserKeyToken(config('services.onesignal.user_token'));

        $this->client = new DefaultApi(
            new GuzzleHttp\Client(),
            $config
        );
    }

    function createPlayerModel($playerId): Player {
        $player = new Player();

        $player->setAppId(config('services.onesignal.app_id'));
        $player->setIdentifier($playerId);
        $player->setDeviceType(1);

        return $player;
    }

    public function bulkSyncUsers($users)
    {
        foreach($users as $user)
        {
            $player = $this->createPlayerModel($user->id);
            $createPlayerResult = $this->client->createPlayer($player);
        }
    }

    public function syncUser($user)
    {
        $player = $this->createPlayerModel($user->id);
        $createPlayerResult = $this->client->createPlayer($player);
    }
}
