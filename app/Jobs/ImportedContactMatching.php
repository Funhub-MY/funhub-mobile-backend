<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserContact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportedContactMatching implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * One chunk per job so a large backlog cannot exceed the worker timeout (which
     * was firing during User::whereIn(...) around high chunk counts).
     */
    public int $timeout = 300;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        UserContact::whereNull('related_user_id')
            ->select('id', 'phone_country_code', 'phone_no')
            ->chunkById(1000, function ($contacts) {
                $this->matchChunk($contacts);

                if ($contacts->count() === 1000) {
                    static::dispatch();
                }

                return false;
            });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\UserContact>  $contacts
     */
    private function matchChunk($contacts): void
    {
        $groupedContacts = $contacts->groupBy(function ($contact) {
            return $contact->phone_country_code . $contact->phone_no;
        });

        $phoneNumbers = $groupedContacts->keys()->all();

        foreach (array_chunk($phoneNumbers, 500) as $chunk) {
            try {
                $users = User::whereIn('full_phone_number', $chunk)
                    ->select('id', 'phone_country_code', 'phone_no', 'full_phone_number')
                    ->get();

                foreach ($users as $user) {
                    $matchingContacts = $groupedContacts->get($user->full_phone_no);

                    if ($matchingContacts) {
                        UserContact::whereIn('id', $matchingContacts->pluck('id'))
                            ->update(['related_user_id' => $user->id]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('[MatchContactToUser] Error matching contacts to users: ' . $e->getMessage());
            }
        }
    }
}
