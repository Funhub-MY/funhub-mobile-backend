<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserReferred
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $referredBy;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($user, $referredBy)
    {
        $this->user = $user;
        $this->referredBy = $referredBy;
    }
}
