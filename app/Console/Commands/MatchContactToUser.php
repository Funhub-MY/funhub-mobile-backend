<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserContact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MatchContactToUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contacts:update {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update user contacts with related user IDs';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $userId = $this->argument('user_id');

        // Get imported contacts without a related user ID
        $contactsQuery = UserContact::whereNull('related_user_id')
            ->select('id', 'phone_country_code', 'phone_no', 'imported_by_id');

        if ($userId) {
            $contactsQuery->where('imported_by_id', $userId);
        }

        $contacts = $contactsQuery->get();

        $this->info('Total contacts to sync: ' . $contacts->count());

        // Group contacts by phone country code and phone number
        $groupedContacts = $contacts->groupBy(function ($contact) {
            return $contact->phone_country_code . $contact->phone_no;
        });

        // Get unique phone country codes and phone numbers from contacts
        $phoneNumbers = $groupedContacts->keys()->all();

        // Chunk the phone numbers to avoid hitting the maximum query length limit
        foreach (array_chunk($phoneNumbers, 500) as $chunk) {
            // Find matching users for the current chunk of phone numbers
            $users = User::whereIn(DB::raw('CONCAT(phone_country_code, phone_no)'), $chunk)
                ->select('id', 'phone_country_code', 'phone_no')
                ->get();

            $this->info('Total users matched for imported contacts/per 500 chunks of numbers: ' . $users->count());

            // Update the related user ID for matching contacts
            foreach ($users as $user) {
                $matchingContacts = $groupedContacts->get($user->phone_country_code . $user->phone_no);

                if ($matchingContacts) {
                    UserContact::whereIn('id', $matchingContacts->pluck('id'))
                        ->update(['related_user_id' => $user->id]);

                    // Display information about the updated user ID
                    $this->info('User ID ' . $user->id . ' has been added as a related user.');
                }
            }
        }

        $this->info('User contacts have been updated successfully.');

        return 0;
    }
}
