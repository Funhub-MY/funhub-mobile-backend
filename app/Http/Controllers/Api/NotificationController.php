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
    public function getNotifications()
    {
        // get user database notifications
        $notifications = auth()->user()->notifications()
        ->orderBy('created_at', 'desc')
        ->paginate(
            config('app.paginate_per_page')
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
            'message' => 'Notifications marked as read.'
        ]);
    }
}
