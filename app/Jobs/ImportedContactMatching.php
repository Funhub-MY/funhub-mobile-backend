<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserContact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportedContactMatching implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
                $groupedContacts = $contacts->groupBy(function ($contact) {
                    return $contact->phone_country_code . $contact->phone_no;
                });

                $phoneNumbers = $groupedContacts->keys()->all();

                foreach (array_chunk($phoneNumbers, 500) as $chunk) {
                    try {
                        $users = User::whereIn(DB::raw('CONCAT(phone_country_code, phone_no)'), $chunk)
                            ->select('id', 'phone_country_code', 'phone_no')
                            ->get();

                        foreach ($users as $user) {
                            $matchingContacts = $groupedContacts->get($user->phone_country_code . $user->phone_no);

                            if ($matchingContacts) {
                                UserContact::whereIn('id', $matchingContacts->pluck('id'))
                                    ->update(['related_user_id' => $user->id]);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('[MatchContactToUser] Error matching contacts to users: ' . $e->getMessage());
                    }
                }
            });
    }
}
