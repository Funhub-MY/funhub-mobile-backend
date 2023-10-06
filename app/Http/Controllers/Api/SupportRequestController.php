<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupportRequestCategoryResource;
use App\Http\Resources\SupportRequestMessageResource;
use App\Http\Resources\SupportRequestResource;
use App\Models\SupportRequest;
use App\Models\SupportRequestCategory;
use App\Models\SupportRequestMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupportRequestController extends Controller
{
    /**
     * Get My Support Requests
     *
     * @param Request $request
     * @return void
     *
     * @group Help Center
     * @subgroup Support Requests
     * @bodyParam status string optional Status of support request. Example: 0=pending,1=in progress,2=pending info,3=closed,4=reopened,5=invalid
     * @bodyParam category_ids array optional Array of category ids. Example: [1,2,3]
     * @bodyParam query string optional Search query. Example: my support request
     * @bodyParam limit integer optional Limit of results per page. Example: 10
     *
     * @response scenario="success" {
     * "data": []
     * }
     */
    public function index(Request $request) {
        // get all my own support requests
        $query = $request->user()->supportRequests()
            ->with('messages')
            ->join(DB::raw('(SELECT id as spm_id,created_at,support_request_id from support_requests_messages) AS support_requests_messages'), 'support_requests.id', '=', 'support_requests_messages.support_request_id')
            ->orderBy('support_requests_messages.created_at', 'desc');

        if ($request->has('query')) {
            $query->where('title', 'like', '%' . $request->query . '%');
        }

        if ($request->has('status')) {
            // check whether status number is valid
            if (!in_array($request->status, array_keys(SupportRequest::STATUS))) {
                return response()->json([
                    'message' => 'Invalid status'
                ], 422);
            }
            $query->where('status', $request->status);
        }

        if ($request->has('category_ids')) {
            $query->whereIn('category_id', $request->category_ids);
        }

        $results = $query->paginate(
            ($request->has('limit') ? $request->limit : config('app.paginate_per_page'))
        );

        return SupportRequestResource::collection($results);
    }

    /**
     * Raise Support Request
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Help Center
     * @subgroup Support Requests
     * @bodyParam category_id integer required Category id. Example: 1
     * @bodyParam title string required Title of support request. Example: My support request
     * @bodyParam message string required Message to send. Example: This is my message
     * @bodyParam media_ids array optional Array of media ids. Example: [1,2,3]
     *
     * @response scenario="success" {
     * "message": {},
     * "request": {}
     * }
     */
    public function postRaiseSupportRequest(Request $request)
    {
        $this->validate($request, [
            'category_id' => 'required|exists:support_requests_categories,id',
            'title' => 'required|string',
            'message' => 'required|string',
            'media_ids' => 'nullable|array',
        ]);

        // create new support request
        $supportRequest = $request->user()->supportRequests()->create([
            'category_id' => $request->category_id,
            'title' => $request->title,
            'status' => SupportRequest::STATUS_PENDING
        ]);

        // create a new message attach to this
        $message = $supportRequest->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $request->message
        ]);

        // move media to attached to message
        if ($request->has('media_ids')) {
            $userUploads = auth()->user()->getMedia('support_uploads')
                ->whereIn('id', $request->media_ids);
            $userUploads->each(function ($media) use ($message) {
                $media->move($message, SupportRequestMessage::MEDIA_COLLECTION_NAME);
            });
        }

        $supportRequest->load('messages');

        return response()->json([
            'message' => new SupportRequestMessageResource($message),
            'request' => new SupportRequestResource($supportRequest)
        ]);
    }

    /**
     * Reply to Support Request
     *
     * @param SupportRequest $supportRequest
     * @param Request $request
     * @return void
     *
     * @group Help Center
     * @subgroup Support Requests
     * @urlParam id required Support Request ID. Example: 1
     * @bodyParam message string required Message to send. Example: This is my message
     * @bodyParam media_ids array optional Array of media ids. Example: [1,2,3]
     * @response scenario="success" {
     * "message": {},
     * "request": {}
     * }
     */
    public function postReplyToSupportRequest(SupportRequest $supportRequest, Request $request)
    {
        $this->validate($request, [
            'message' => 'required|string',
            'media_ids' => 'nullable|array',
        ]);

        // check if supportRequest requestor is same as current user
        if ($supportRequest->requestor_id != $request->user()->id) {
            return response()->json([
                'message' => 'You are not allowed to reply to this support request'
            ], 403);
        }

        // create a new message attach to this
        $message = $supportRequest->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $request->message
        ]);

        // move media to attached to message
        if ($request->has('media_ids')) {
            $userUploads = auth()->user()->getMedia('support_uploads')
                ->whereIn('id', $request->media_ids);
            $userUploads->each(function ($media) use ($message) {
                $media->move($message, SupportRequestMessage::MEDIA_COLLECTION_NAME);
            });
        }

        $supportRequest->load('messages');
        return response()->json([
            'message' => new SupportRequestMessageResource($message),
            'request' => new SupportRequestResource($supportRequest)
        ]);
    }

    /**
     * Get Messages of Support Request
     *
     * @param SupportRequest $supportRequest
     * @return void
     *
     * @group Help Center
     * @subgroup Support Requests
     * @urlParam id required Support Request ID. Example: 1
     * @response scenario="success" {
     * "data": []
     * }
     */
    public function getMessagesOfSupportRequest(SupportRequest $supportRequest)
    {
        // check if supportRequest requestor is same as current user
        if ($supportRequest->requestor_id != auth()->user()->id) {
            return response()->json([
                'message' => 'You are not allowed to reply to this support request'
            ], 403);
        }

        $query = $supportRequest->messages()
            ->orderBy('created_at', 'desc');

        $results = $query->paginate(config('app.paginate_per_page'));

        return SupportRequestMessageResource::collection($results);
    }

    /**
     * Resolve Support Request
     *
     * @param SupportRequest $supportRequest
     * @return JsonResponse
     *
     * @group Help Center
     * @subgroup Support Requests
     * @urlParam id required Support Request ID. Example: 1
     * @response scenario="success" {
     * "message": "Support request resolved and closed"
     * }
     */
    public function postResolveSupportRequest(SupportRequest $supportRequest)
    {
        // check if supportRequest requestor is same as current user
        if ($supportRequest->requestor_id != auth()->user()->id) {
            return response()->json([
                'message' => 'You are not allowed to reply to this support request'
            ], 403);
        }

        $supportRequest->update([
            'status' => SupportRequest::STATUS_CLOSED
        ]);

        return response()->json([
            'message' => 'Support request resolved and closed'
        ]);
    }

    /**
     * Get Support Request Categories
     *
     * @return JsonResponse
     *
     * @group Help Center
     * @subgroup Support Requests
     * @bodyParam type string optional Type of support request category. Example: 0=general,1=product,2=account,3=other
     */
    public function getSupportRequestsCategories(Request $request)
    {
        $query = SupportRequestCategory::published();

        if ($request->has('type')) {
            // validate type
            if (!in_array($request->type, array_keys(SupportRequestCategory::TYPES))) {
                return response()->json([
                    'message' => 'Invalid type'
                ], 422);
            }
            $query->where('type', $request->type);
        }

        $supportRequestsCategories = $query->get();

        return SupportRequestCategoryResource::collection($supportRequestsCategories);
    }


    /**
     * Upload Attachments(Images) for Support Messages
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Help Center
     * @subgroup Support Requests
     * @bodyParam images file required The images to upload.
     * @response scenario=success {
     * "uploaded": [
     *     {
     *        "id": 1,
     *        "name": "image.jpg",
     *        "url": "http://localhost:8000/storage/user_uploads/1/image.jpg",
     *        "size": 12345,
     *        "type": "image/jpeg"
     *    }
     * ]
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["images": ["The images field is required."] ]}
     */
    public function postAttachmentsUpload(Request $request)
    {
        $this->validate($request,[
            'images' => 'required',
            'images.*' => 'image|mimes:jpg,jpeg,png,gif,heic'
        ]);

        $user = auth()->user();
        $images = [];
        // if request images is not array wrap it in array
        if (!is_array($request->images)) {
            // upload via spatie medialibrary
            // single image

            $uploaded = $user->addMedia($request->images)
                ->toMediaCollection(
                    'support_uploads',
                    (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')),
                );
            return response()->json([
                'uploaded' => [
                    [
                        'id' => $uploaded->id,
                        'name' => $uploaded->file_name,
                        'url' => $uploaded->getUrl(),
                        'size' => $uploaded->size,
                        'type' => $uploaded->mime_type,
                    ],
                ],
            ]);
        } else {
            // multiple images
            $uploaded = collect($request->images)->map(function ($image) use ($user) {
                return $user->addMedia($image)
                    ->toMediaCollection(
                        'support_uploads',
                        (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')),
                );
            });
            $uploaded->each(function ($image) use (&$images) {
                $images[] = [
                    'id' => $image->id,
                    'name' => $image->file_name,
                    'url' => $image->getUrl(),
                    'size' => $image->size,
                    'type' => $image->mime_type,
                ];
            });
            return response()->json([
                'uploaded' => $images,
            ]);
        }
    }

}
