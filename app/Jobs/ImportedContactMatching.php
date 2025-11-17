<?php

namespace App\Jobs;

use Exception;
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
        // Get all imported contacts without a related user ID
        $contacts = UserContact::whereNull('related_user_id')
            ->select('id', 'phone_country_code', 'phone_no')
            ->get();

        // Group contacts by phone country code and phone number
        $groupedContacts = $contacts->groupBy(function ($contact) {
            return $contact->phone_country_code . $contact->phone_no;
        });

        // Get unique phone country codes and phone numbers from contacts
        $phoneNumbers = $groupedContacts->keys()->all();

        // Chunk the phone numbers to avoid hitting the maximum query length limit
        foreach (array_chunk($phoneNumbers, 500) as $chunk) {
            try {
                // Find matching users for the current chunk of phone numbers
                $users = User::whereIn(DB::raw('CONCAT(phone_country_code, phone_no)'), $chunk)
                    ->select('id', 'phone_country_code', 'phone_no')
                    ->get();

                // Update the related user ID for matching contacts
                foreach ($users as $user) {
                    $matchingContacts = $groupedContacts->get($user->phone_country_code . $user->phone_no);

                    if ($matchingContacts) {
                        UserContact::whereIn('id', $matchingContacts->pluck('id'))
                            ->update(['related_user_id' => $user->id]);
                    }
                }
            } catch (Exception $e) {
                Log::error('[MatchContactToUser] Error matching contacts to users: ' . $e->getMessage());
            }
        }
    }
}
