<?php

namespace App\Jobs;

use Exception;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class UpdateUserLastLang implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $lastLang;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, string $lastLang)
    {
        $this->user = $user;
        $this->lastLang = $lastLang;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::alert('[UpdateUserLastLang] Updating last language for user ' . $this->user->id);
        // Update the user's last_lang column
        $this->user->update(['last_lang' => $this->lastLang]);
    }

    public function failed(Exception $exception)
    {
        Log::error($exception->getMessage());
    }
}
