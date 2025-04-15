<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OnAccountRestricted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $previousRestricted;
    public $previousRestrictedUntil;
    public $newRestricted;
    public $newRestrictedUntil;

    public function __construct(User $user, $previousRestricted, $previousRestrictedUntil, $newRestricted, $newRestrictedUntil)
    {
        $this->user = $user;
        $this->previousRestricted = $previousRestricted;
        $this->previousRestrictedUntil = $previousRestrictedUntil;
        $this->newRestricted = $newRestricted;
        $this->newRestrictedUntil = $newRestrictedUntil;
    }
}
