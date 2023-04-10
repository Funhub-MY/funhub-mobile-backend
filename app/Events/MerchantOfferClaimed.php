<?php

namespace App\Events;

use App\Models\MerchantOffer;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MerchantOfferClaimed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $offer, $user;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(MerchantOffer $offer, User $user)
    {
        $this->offer = $offer;
        $this->user = $user;
    }
}
