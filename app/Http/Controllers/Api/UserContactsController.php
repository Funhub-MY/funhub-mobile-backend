<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserContactsController extends Controller
{
    /**
     * Import User Contacts
     *
     * @param Request $request
     * @return void
     *
     * @group User
     * @subgroup Contacts
     * @bodyParam contacts array required. Example: [{"country_code": "60", "phone_no": "123456789", "name": "John Doe"}]
     * @response {
     * "message": "Contacts imported successfully"
     */
    public function postImportContacts(Request $request)
    {
        $this->validate($request, [
            'contacts' => 'required|array',
            'contacts.*.country_code' => 'required|string',
            'contacts.*.phone_no' => 'required|string',
        ]);

        $contacts = $request->input('contacts');

        $importedContacts = [];

        foreach ($contacts as $contact) {
            // check phone no has prefix 0 remove it first
            $phone_no = $contact['phone_no'];
            if (substr($phone_no, 0, 1) == '0') {
                $phone_no = substr($phone_no, 1);
            } else if (substr($phone_no, 0, 2) == '60') {
                $phone_no = substr($phone_no, 2);
            }

            // check phone no has prefix + remove it first
            if (substr($phone_no, 0, 1) == '+') {
                $phone_no = substr($phone_no, 1);
            }

            // create user contat if country code and phone no havent created before
            $importedContacts[] = UserContact::firstOrCreate([
                'phone_country_code' => $contact['country_code'],
                'phone_no' => $phone_no,
            ], [
                'name' => (isset($contact['name']) ? $contact['name'] : '-'),
                'imported_by_id' => auth()->user()->id,
            ]);
        }

        // after import, check related user id match with users table based on phone_country_code and phone_no
        $importedContacts = collect($importedContacts);

        // combine into one country code and phone no array
        $importedNumbers = $importedContacts->map(function ($contact) {
            $contact->full_phone_no = $contact->phone_country_code . $contact->phone_no;
            return $contact;
        });

        // match with users table phone_country_code and phone_no
        $users = User::whereIn(DB::raw('CONCAT(phone_country_code, phone_no)'), $importedNumbers->pluck('full_phone_no')->toArray())
            ->get();


        // matched users, update related_user_id of related imported
        foreach ($users as $user) {
            $matchingUserId = $importedContacts->filter(function ($contact) use ($user) {
                return $contact->full_phone_no == $user->phone_country_code . $user->phone_no;
            })->pluck('id');

            // one query to update contact with matched user id
            UserContact::whereIn('id', $matchingUserId)->update([
                'related_user_id' => $user->id,
            ]);
        }

        return response()->json([
            'message' => 'Contacts imported successfully',
        ]);
    }

    /**
     * Get Contacts not yet followed
     *
     * @return JsonResponse
     *
     * @group User
     * @subgroup Contacts
     * @response scenario=success {
     * "users": []
     * }
     * @response scenario=error {
     * "message": "No friends found"
     * }
     */
    public function getContactsNotYetFollowed()
    {
        $contacts = UserContact::whereNull('related_user_id')
            ->where('imported_by_id', auth()->user()->id)
            ->get();

        // find current user followings
        $following_ids = auth()->user()->followings()->pluck('following_id')->toArray();

        Log::info('following_ids', $following_ids);

        // filter contacts not yet followed by current user
        $contacts = $contacts->filter(function ($contact) use ($following_ids) {
            return !in_array($contact->related_user_id, $following_ids);
        });

        Log::info('contacts', json_encode($contacts));

        // return user list by contacts's related_user_id
        $contacts = User::whereIn('id', $contacts->pluck('related_user_id')->toArray())
            ->get();

        if ($contacts->count() == 0) {
            return response()->json([
                'message' => 'No friends found',
            ]);
        }

        return response()->json([
            'users' => UserResource::collection($contacts),
        ]);
    }

}
