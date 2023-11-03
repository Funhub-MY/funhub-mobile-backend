<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\MerchantContact;

class MerchantContactController extends Controller
{
    /**
     * Submit Merchant Contact Information
     *
     * Allows users to submit merchant contact information via the API.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Merchant
     * @subgroup Merchant Contacts
     *
     * @bodyParam name string required The name of the contact person. Example: Alex Koh
     * @bodyParam email string required The email address of the contact person. Example: alex@funhub.my
     * @bodyParam tel_no string required The telephone number of the contact person. Example: 182036794
     * @bodyParam company_name string required The name of the company. Example: Funhub TV
     * @bodyParam business_type string required The type of the business. Example: others
     * @bodyParam other_business_type string required_if:business_type,others The type of the business. Example: IT Consult
     * @bodyParam message_type string required The category of the message. Example: General Inquiry
     * @bodyParam message text required The message or remarks. Example: This is a sample message.
     *
     * @response scenario=success {
     * "message": "Merchant contact information submitted successfully"
     * }
     * @response status=422 scenario="Invalid Form Fields" {
     * "errors": {
     *     "name": ["The Name field is required."],
     *     "email": ["The Email field must be a valid email address."],
     *     "tel_no": ["The Phone Number field is required."],
     *     "company_name": ["The Company Name field is required."],
     *     "business_type": ["The Business Type field is required."],
     *     "message_type": ["The Message Category field is required."],
     *     "message": ["The Message field is required."]
     *     }
     * }
     */
    public function postMerchantContact(Request $request)
    {
        // Validate the request data
        $validatedData = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'tel_no' => 'required|string',
            'company_name' => 'required|string',
            'business_type' => 'required|string',
            'other_business_type' => 'nullable|string|required_if:business_type,others',
            'message_type' => 'required|string',
            'message' => 'required',
        ]);

        //prepare data
        if ($request->input('business_type') === 'others') {
            $validatedData['business_type'] = $request->input('other_business_type');
        }

        $createdBy = auth()->id();

        $data = array_merge($validatedData, ['created_by' => $createdBy]);

        // Create a new merchant contact record
        $merchantContact = MerchantContact::create($data);

        return response()->json(['message' => 'Merchant contact information submitted successfully'], 200);
    }
}
