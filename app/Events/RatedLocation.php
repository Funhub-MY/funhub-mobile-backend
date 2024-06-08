<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RatedLocation
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $location;
    public $user;
    public $rating;
    public $articleId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($location, $user, $rating, $articleId = null)
    {
        $this->location = $location;
        $this->user = $user;
        $this->rating = $rating;
        $this->articleId = $articleId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
