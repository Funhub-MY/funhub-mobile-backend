<?php

namespace App\Events;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MissionCompletedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The mission instance.
     *
     * @var Mission
     */
    public $mission;

    /**
     * The user instance.
     *
     * @var User
     */
    public $user;

    /**
     * Create a new event instance.
     *
     * @param Mission $mission
     * @param User $user
     * @return void
     */
    public function __construct(Mission $mission, User $user)
    {
        $this->mission = $mission;
        $this->user = $user;
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
