<?php

namespace App\Events;

use App\Models\Store;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RatedStore
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $store, $user;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Store $store, User $user)
    {
        $this->store = $store;
        $this->user = $user;
    }
}
