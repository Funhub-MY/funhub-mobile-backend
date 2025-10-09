<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Rsvp;

class RsvpController extends Controller
{
    public function postRsvpRegister(Request $request)
    {
        // Check if email already exists
        if (Rsvp::where('email', $request->email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This email is already registered for RSVP.',
            ], 409);
        }

        // Check if phone number already exists
        if (Rsvp::where('phone_no', $request->phone_no)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This phone number is already registered for RSVP.',
            ], 409);
        }

        $data = Rsvp::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone_no' => $request->phone_no,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'RSVP made successfully',
            'data'    => $data,
        ], 201);
    }
}
