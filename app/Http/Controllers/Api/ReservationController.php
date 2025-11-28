<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\Campaign;
use App\Models\ReservationFormField;
use App\Services\BadgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReservationController extends Controller
{
    /**
     * Get Form Fields for Campaign
     *
     * @param int $campaignId
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Reservations
     * @urlParam campaign_id integer required Campaign ID. Example: 1
     * @response scenario="success" {
     * "campaign": {},
     * "form_fields": [],
     * "requires_approval": true
     * }
     */
    public function getFormFields($campaignId)
    {
        $campaign = Campaign::find($campaignId);

        if (!$campaign) {
            return response()->json([
                'message' => 'Campaign not found'
            ], 404);
        }

        $formFieldConfig = ReservationFormField::where('campaign_id', $campaignId)
            ->where('is_active', true)
            ->first();

        return response()->json([
            'campaign' => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'requires_approval' => $campaign->requires_approval ?? false,
            ],
            'form_fields' => $formFieldConfig ? $formFieldConfig->form_fields : [],
        ]);
    }

    /**
     * Create Reservation
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Reservations
     * @bodyParam campaign_id integer required Campaign ID. Example: 1
     * @bodyParam reservation_date string required Reservation date. Example: 2025-12-01 10:00:00
     * @bodyParam form_data object required Form field values. Example: {"name": "John Doe", "email": "john@example.com"}
     * @bodyParam files object optional File uploads. Example: {"identity_card": <file>, "proof_of_address": <file>}
     * @response scenario="success" {
     * "message": "Reservation created successfully",
     * "reservation": {}
     * }
     */
    public function store(Request $request)
    {
        $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'reservation_date' => 'required|date',
            'form_data' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $campaign = Campaign::findOrFail($request->campaign_id);
            $formFieldConfig = ReservationFormField::where('campaign_id', $request->campaign_id)
                ->where('is_active', true)
                ->first();

            if (!$formFieldConfig) {
                return response()->json([
                    'message' => 'Form configuration not found for this campaign'
                ], 404);
            }

            // Get form fields to validate field_keys
            $formFields = collect($formFieldConfig->form_fields);
            $formData = [];
            $fileFields = [];
            
            // Get all valid field_keys from campaign form fields
            $validFieldKeys = $formFields->pluck('field_key')->toArray();
            
            // Validate that all submitted form_data keys exist in campaign_form_fields
            $submittedKeys = array_keys($request->form_data ?? []);
            $invalidKeys = array_diff($submittedKeys, $validFieldKeys);
            
            if (!empty($invalidKeys)) {
                return response()->json([
                    'message' => 'Invalid form fields submitted',
                    'errors' => [
                        'form_data' => [
                            'The following fields are not defined in the campaign form: ' . implode(', ', $invalidKeys)
                        ]
                    ],
                    'valid_fields' => $validFieldKeys
                ], 422);
            }
            
            // Validate required fields (if is_required is defined in form_fields)
            $requiredFields = $formFields->filter(function ($field) {
                return isset($field['is_required']) && $field['is_required'] === true;
            })->pluck('field_key')->toArray();
            
            $missingRequiredFields = array_diff($requiredFields, $submittedKeys);
            
            if (!empty($missingRequiredFields)) {
                return response()->json([
                    'message' => 'Required fields are missing',
                    'errors' => [
                        'form_data' => [
                            'The following required fields are missing: ' . implode(', ', $missingRequiredFields)
                        ]
                    ],
                    'required_fields' => $requiredFields
                ], 422);
            }

            // Store all form_data directly using field_key as the key
            foreach ($request->form_data as $fieldKey => $value) {
                $fieldConfig = $formFields->firstWhere('field_key', $fieldKey);
                
                if ($fieldConfig) {
                    // Handle file fields separately (will be stored after upload)
                    if ($fieldConfig['field_type'] === 'file') {
                        $fileFields[] = $fieldKey;
                    } else {
                        // Store directly using field_key
                        $formData[$fieldKey] = $value;
                    }
                }
            }
            
            // Validate file fields match campaign_form_fields
            if ($request->hasFile('files')) {
                $submittedFileKeys = array_keys($request->file('files'));
                $invalidFileKeys = array_diff($submittedFileKeys, $fileFields);
                
                if (!empty($invalidFileKeys)) {
                    return response()->json([
                        'message' => 'Invalid file fields submitted',
                        'errors' => [
                            'files' => [
                                'The following file fields are not defined in the campaign form: ' . implode(', ', $invalidFileKeys)
                            ]
                        ],
                        'valid_file_fields' => $fileFields
                    ], 422);
                }
            }

            // Create reservation with form_data
            $reservation = Reservation::create([
                'user_id' => auth()->user()->id,
                'campaign_id' => $request->campaign_id,
                'reservation_date' => $request->reservation_date,
                'amount' => $request->amount ?? null,
                'status' => 'pending',
                'approval_status' => $campaign->requires_approval ? 'pending' : null,
                'form_data' => $formData,
            ]);

            // Handle file uploads
            if ($request->hasFile('files')) {
                $files = $request->file('files');
                $fileData = [];
                
                foreach ($files as $fieldKey => $file) {
                    // Handle both single file and array of files
                    if (is_array($file)) {
                        // Multiple files for same field_key (e.g., files[content_screenshots][0], files[content_screenshots][1])
                        $fileArray = [];
                        foreach ($file as $index => $singleFile) {
                            if ($singleFile->isValid() && in_array($fieldKey, $fileFields)) {
                                $media = $reservation->addMediaFromRequest("files.{$fieldKey}.{$index}")
                                    ->withCustomProperties([
                                        'field_key' => $fieldKey,
                                    ])
                                    ->toMediaCollection(Reservation::MEDIA_COLLECTION_FORM_FILES);

                                $fileArray[] = [
                                    'media_id' => $media->id,
                                    'url' => $media->getUrl(),
                                    'name' => $media->name,
                                    'size' => $media->size,
                                ];
                            }
                        }
                        if (!empty($fileArray)) {
                            $fileData[$fieldKey] = $fileArray;
                        }
                    } else {
                        // Single file
                        if ($file->isValid() && in_array($fieldKey, $fileFields)) {
                            $media = $reservation->addMediaFromRequest("files.{$fieldKey}")
                                ->withCustomProperties([
                                    'field_key' => $fieldKey,
                                ])
                                ->toMediaCollection(Reservation::MEDIA_COLLECTION_FORM_FILES);

                            // Store file info in form_data using field_key
                            $fileData[$fieldKey] = [
                                'media_id' => $media->id,
                                'url' => $media->getUrl(),
                                'name' => $media->name,
                                'size' => $media->size,
                            ];
                        }
                    }
                }

                // Merge file data into form_data
                if (!empty($fileData)) {
                    $formData = array_merge($formData, $fileData);
                    $reservation->update(['form_data' => $formData]);
                }
            }

            // Award badge if campaign doesn't require approval
            $awardedBadge = null;
            if (!$campaign->requires_approval) {
                $badgeService = new BadgeService();
                $userBadge = $badgeService->awardBadgeForReservation($reservation);
                if ($userBadge) {
                    $awardedBadge = [
                        'id' => $userBadge->badge->id,
                        'name' => $userBadge->badge->name,
                        'color' => $userBadge->badge->color,
                    ];
                }
            }

            DB::commit();

            $response = [
                'message' => 'Reservation created successfully',
                'reservation' => new ReservationResource($reservation),
            ];
            
            if ($awardedBadge) {
                $response['badge_awarded'] = $awardedBadge;
            }

            return response()->json($response, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reservation creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to create reservation',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get User Reservations
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Reservations
     * @queryParam campaign_id integer optional Filter by campaign. Example: 1
     * @queryParam status string optional Filter by status. Example: pending
     * @response scenario="success" {
     * "reservations": []
     * }
     */
    public function index(Request $request)
    {
        $query = Reservation::where('user_id', auth()->user()->id)
            ->with(['campaign', 'approvedBy']);

        if ($request->has('campaign_id')) {
            $query->where('campaign_id', $request->campaign_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $reservations = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'reservations' => ReservationResource::collection($reservations),
        ]);
    }

    /**
     * Get Reservation Details
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Reservations
     * @urlParam id integer required Reservation ID. Example: 1
     * @response scenario="success" {
     * "reservation": {}
     * }
     */
    public function show($id)
    {
        $reservation = Reservation::where('user_id', auth()->user()->id)
            ->with(['campaign', 'approvedBy'])
            ->findOrFail($id);

        return response()->json([
            'reservation' => new ReservationResource($reservation),
        ]);
    }

    /**
     * Update Reservation (for user to update their own reservation)
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Reservations
     * @urlParam id integer required Reservation ID. Example: 1
     * @bodyParam reservation_date string optional Reservation date. Example: 2025-12-01 10:00:00
     * @bodyParam form_data object optional Form field values. Example: {"name": "John Doe"}
     * @response scenario="success" {
     * "message": "Reservation updated successfully",
     * "reservation": {}
     * }
     */
    public function update(Request $request, $id)
    {
        $reservation = Reservation::where('user_id', auth()->user()->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $request->validate([
            'reservation_date' => 'sometimes|date',
            'form_data' => 'sometimes|array',
        ]);

        try {
            DB::beginTransaction();

            if ($request->has('reservation_date')) {
                $reservation->reservation_date = $request->reservation_date;
            }

            if ($request->has('form_data')) {
                // Simply merge new form_data with existing (no mapping needed)
                $existingFormData = $reservation->form_data ?? [];
                $newFormData = $request->form_data;
                
                // Merge arrays, new data overwrites existing
                $reservation->form_data = array_merge($existingFormData, $newFormData);
            }

            $reservation->save();

            DB::commit();

            return response()->json([
                'message' => 'Reservation updated successfully',
                'reservation' => new ReservationResource($reservation->fresh()),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reservation update failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to update reservation',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Cancel Reservation
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Reservations
     * @urlParam id integer required Reservation ID. Example: 1
     * @response scenario="success" {
     * "message": "Reservation cancelled successfully"
     * }
     */
    public function cancel($id)
    {
        $reservation = Reservation::where('user_id', auth()->user()->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->findOrFail($id);

        $reservation->update([
            'status' => 'cancelled'
        ]);

        return response()->json([
            'message' => 'Reservation cancelled successfully',
            'reservation' => new ReservationResource($reservation->fresh()),
        ]);
    }
}

