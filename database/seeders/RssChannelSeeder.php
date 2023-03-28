<?php

namespace Database\Seeders;

use App\Models\RssChannel;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RssChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // create base user for each channel
        $goody25 = User::firstOrCreate(
            [
                'email' => 'goody25_rss@email.com'
            ],
            [
                'name' => 'Goody25',
                'password' => bcrypt('abcd1234')
            ]
        );
        $goody_my = User::firstOrCreate(
            [
            'email' => 'goodymy_rss@email.com'
            ],
            [
                'name' => 'GoodyMy',
                'password' => bcrypt('abcd1234')
            ]
        );
        $mortify = User::firstOrCreate(
            [
                'email' => 'mortify_rss@email.com',
            ],
            [
                'name' => 'Mortify',
                'password' => bcrypt('abcd1234')
            ]
        );
        $noodou = User::firstOrCreate(
            [
            'email' => 'noodou_rss@email.com',
            ],
            [
                'name' => 'Noodou',
                'password' => bcrypt('abcd1234')
            ]
        );
        // RSS Channel creation
        if ($goody25) {
            $goody25_channel = RssChannel::firstOrCreate(
                [
                    'channel_name' => $goody25->name,
                    'user_id' => $goody25->id
                ],
                [
                    'channel_url' => 'https://www.goody25.com/feed'
                ]
            );
        }
        if ($goody_my) {
            $goodymy_channel = RssChannel::firstOrCreate(
                [
                    'channel_name' => $goody_my->name,
                    'user_id' => $goody_my->id
                ],
                [
                    'channel_url' => 'https://www.goodymy.com/feed'
                ]
            );
        }
        if ($mortify) {
            $mortify_channel = RssChannel::firstOrCreate(
                [
                    'channel_name' => $mortify->name,
                    'user_id' => $mortify->id
                ],
                [
                    'channel_url' => 'https://www.moretify.com/feed'
                ]
            );
        }
        if ($noodou) {
            $noodou_channel = RssChannel::firstOrCreate(
                [
                    'channel_name' => $noodou->name,
                    'user_id' => $noodou->id
                ],
                [
                    'channel_url' => 'https://noodou.com/feed'
                ]
            );
        }

    }
}
