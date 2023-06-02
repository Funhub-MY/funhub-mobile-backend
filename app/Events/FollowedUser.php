<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FollowedUser
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user, $followedUser;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(User $user, User $followedUser)
    {
        $this->user = $user;
        $this->followedUser = $followedUser;
    }
}
