<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get latest notifications of the user
     * Ordered by latest one
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group Notifications
     * @bodyParam per_page int The number of items per page. Example: 10
     * @response scenario=success {
     * "data": [
     * {
     *    "id": 1,
     *    "message" : "A commented on your Article",
     *    "object" : "article",
     *    "object_id" : 1,
     *    "link_to_url" : "http://localhost:8000/articles/1",
     *    "link_to_object" : "article",
     *    "action" : "comment",
     *    "from" : "user",
     *    "from_id" : 1,
     *    "created_at_raw" : "2021-08-04T07:00:00.000000Z",
     *    "created_at" : "1 day ago"
     * }
     * ]
     *
     */
    public function getNotifications(Request $request)
    {
        // get user database notifications
        $notifications = auth()->user()->notifications()
        ->orderBy('created_at', 'desc')
        ->paginate(
            $request->input('per_page', config('app.paginate_per_page')),
        );

        // get all user ids from current load
        $userIds = $notifications->pluck('data.from_id')->unique();

        // load users
        $users = User::whereIn('id', $userIds)->get();

        // map UserResource of $users into the $notifications results
        $notifications->getCollection()->transform(function ($notification) use ($users) {
            $notification->from_user = $users->where('id', $notification->data['from_id'])->first();
            return $notification;
        });

        return NotificationResource::collection($notifications);
    }

    /**
     * Mark all notifications as read
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group Notifications
     * @response scenario=success {
     * "message": "Notifications marked as read."
     * }
     */
    public function postMarkUnreadNotificationAsRead()
    {
        $user = auth()->user();
            
        foreach ($user->unreadNotifications as $notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'message' => __('messages.success.notification_controller.Notifications_marked_as_read')
        ]);
    }

    /**
     * Mark a Single Notification as Read by Notification ID
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group Notifications
     * @bodyParam notification_id string required The notification id. Example: 058d0c3d-1028-4660-b905-7e30ad7eee9c
     * @response scenario=success {
     * "message": "Notification marked as read."
     * }
     */
    public function postMarkSingleUnreadNotificationAsRead(Request $request)
    {
        $this->validate($request, [
            'notification_id' => 'required',
        ]);

        $user = auth()->user();
        
        // get notification by notificaiton id
        $notification = $user->notifications()->where('id', $request->notification_id)->first();
        if (!$notification) {
            return response()->json([
                'message' => __('messages.error.notification_controller.Notification_not_found')
            ], 404);
        } else {
            $notification->markAsRead();
        }

        return response()->json([
            'message' => __('messages.success.notification_controller.Notifications_marked_as_read')
        ]);
    }
}
