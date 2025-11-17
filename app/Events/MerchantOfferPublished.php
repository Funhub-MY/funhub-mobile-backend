<?php

namespace App\Events;

use App\Models\MerchantOffer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MerchantOfferPublished
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $merchantOffer;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(MerchantOffer $merchantOffer)
    {
        $this->merchantOffer = $merchantOffer;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
