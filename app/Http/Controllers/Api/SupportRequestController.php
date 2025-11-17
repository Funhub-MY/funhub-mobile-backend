<?php

namespace App\Http\Controllers\Api;

use Exception;
use Illuminate\Http\JsonResponse;
use App\Models\Article;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\SupportRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\SupportRequestMessage;
use App\Models\SupportRequestCategory;
use App\Http\Resources\SupportRequestResource;
use App\Http\Resources\SupportRequestMessageResource;
use App\Http\Resources\SupportRequestCategoryResource;

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
    public function index(Request $request)
    {
        // get all my own support requests
        $query = $request->user()->supportRequests()
            ->with('messages')
            ->orderBy('created_at', 'desc');

        if ($request->has('query')) {
            $query->where('title', 'like', '%' . $request->query . '%');
        }

        if ($request->has('status')) {
            // check whether status number is valid
            if (!in_array($request->status, array_keys(SupportRequest::STATUS))) {
                return response()->json([
                    'message' => __('messages.error.support_request_controller.Invalid_status')
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
     * @bodyParam supportable string required The type of supportable. Example: article
     * @bodyParam title string required Title of support request. Example: My support request
     * @bodyParam message string required Message to send. Example: This is my message
     * @bodyParam media_ids array optional Array of media ids. Example: [1,2,3]
     * @response scenario="success" {
     * "data": []
     * }
     */
    public function postRaiseSupportRequest(Request $request)
    {
        $this->validate($request, [
            'supportable' => 'nullable|string',
            'supportable_id' => 'required_with:supportable|integer',
            'category_id' => 'required|exists:support_requests_categories,id',
            'title' => 'required|string',
            'message' => 'required|string',
            'media_ids' => 'nullable|array',
        ]);

        if ($request->supportable == 'article') {
            $request->merge(['supportable' => Article::class]);
        }

        // create new support request
        $supportRequest = $request->user()->supportRequests()->create([
            'category_id' => $request->category_id,
            'supportable_type' => $request->supportable,
            'supportable_id' => $request->supportable_id,
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
            $userUploads = auth()->user()->getMedia(SupportRequestMessage::MEDIA_COLLECTION_NAME)
                ->whereIn('id', $request->media_ids);
            $userUploads->each(function ($media) use ($message) {
                $media->move($message, SupportRequestMessage::MEDIA_COLLECTION_NAME);
            });
        }

        // create a default system message for the support request if there is user with 'Support' role
        try {
            $supportUser = User::whereHas('roles', function ($query) {
                $query->where('name', 'Support');
            })->firstOrFail();

            $systemMessage = $supportRequest->messages()->create([
                'user_id' => $supportUser->id,
                'support_request_id' =>  $supportRequest->id,
                'message' => "你好，我们已收到你的反馈。\n客服服务时间：星期一至星期五 11.00am - 7.00pm\n我们会在24小时内尽快回复你。\n若遇到周末和公假，回复时间会比较长。还请理解，非常感谢"
            ]);
        } catch (Exception $e) {
            // Catch error if no user with the role 'Support' is found
            Log::error('No user with support role is found');
        }

        $supportRequest->load('messages');

        return [
            'message' => new SupportRequestMessageResource($message),
            'request' => new SupportRequestResource($supportRequest)
        ];
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
                'message' => __('messages.error.support_request_controller.You_are_not_allowed_to_reply_to_this_support_request')
            ], 403);
        }

        // create a new message attach to this
        $message = $supportRequest->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $request->message
        ]);

        // move media to attached to message
        if ($request->has('media_ids')) {
            $userUploads = auth()->user()->getMedia(SupportRequestMessage::MEDIA_COLLECTION_NAME)
                ->whereIn('id', $request->media_ids);
            $userUploads->each(function ($media) use ($message) {
                $media->move($message, SupportRequestMessage::MEDIA_COLLECTION_NAME);
            });
        }

        $supportRequest->load('messages');
        return response()->json([
            'message' => __('messages.error.support_request_controller.New_support_request_message_created'),
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
                'message' => __('messages.error.support_request_controller.You_are_not_allowed_to_reply_to_this_support_request')
            ], 403);
        }

        $query = $supportRequest->messages()
            ->orderBy('created_at', 'desc');

        $results = $query->paginate(30);

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
                'message' => __('messages.error.support_request_controller.You_are_not_allowed_to_reply_to_this_support_request')
            ], 403);
        }

        $supportRequest->update([
            'status' => SupportRequest::STATUS_CLOSED
        ]);

        return response()->json([
            'message' => __('messages.success.support_request_controller.Support_request_resolved_and_closed')
        ]);
    }

    /**
     * Get Support Request Categories
     *
     * @return JsonResponse
     *
     * @group Help Center
     * @subgroup Support Requests
     * @bodyParam type string optional Type of support request category. Example: complain,bug,feature_request,others
     */
    public function getSupportRequestsCategories(Request $request)
    {
        $query = SupportRequestCategory::published();

        $types = explode(',', $request->type ?? '');

        if ($request->has('type')) {
            // validate type
            foreach ($types as $type) {
                if (!in_array($type, SupportRequestCategory::TYPES)) {
                    return response()->json([
                        'message' => __('messages.error.support_request_controller.Invalid_type')
                    ], 422);
                }
            }
            $query->whereIn('type', $types);
        }

        $supportRequestsCategories = $query->get();

        return SupportRequestCategoryResource::collection($supportRequestsCategories);
    }


    /**
     * Upload Attachments(Images) for Support Messages
     *
     * @param Request $request
     * @return JsonResponse
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
        $this->validate($request, [
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
                    SupportRequestMessage::MEDIA_COLLECTION_NAME,
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
                        SupportRequestMessage::MEDIA_COLLECTION_NAME,
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
