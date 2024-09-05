<?php

namespace App\Events;

use App\Models\SupportRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClosedSupportTicket
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    protected $supportRequest;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(SupportRequest $supportRequest)
    {
        $this->supportRequest = $supportRequest;
    }
}
