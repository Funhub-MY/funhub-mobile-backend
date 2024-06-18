<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Http\Resources\UserResource;
use App\Models\Article;
use App\Models\Comment;
use App\Models\User;
use App\Models\UserBlock;
use App\Notifications\TaggedUserInComment;
use App\Traits\QueryBuilderTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CommentController extends Controller
{
    use QueryBuilderTrait;

    /**
     * Get comments on a commentable type (eg. Articles)
     *
     * @param $type string
     * @param $id integer
     * @param Request $request
     * @return \Illuminate\Http\Response
     *
     * @group Article
     * @subgroup Comments
     * @authenticated
     * @bodyParam type string required The type of commentable. Example: article
     * @bodyParam id integer required The id of the commentable. Example: 1
     * @bodyParam replies_per_comment integer Number of replies to show per comment. Example: 3
     * @bodyParam filter string Column to Filter. Example: Filterable columns are: id, commentable_id, commentable_type, body, created_at, updated_at
     * @bodyParam filter_value string Value to Filter. Example: Filterable values are: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10
     * @bodyParam sort string Column to Sort. Example: Sortable columns are: id, commentable_id, commentable_type, body, created_at, updated_at
     * @bodyParam order string Direction to Sort. Example: Sortable directions are: asc, desc
     * @bodyParam limit integer Per Page Limit Override. Example: 10
     * @bodyParam offset integer Offset Override. Example: 0
     *
     * @response scenario=success {
     *  "data": [],
     *  "links": {},
     *  "meta": {
     *     "current_page": 1,
     *   }
     * }
     *
     * @response status=404 scenario="Not Found"
     */
    public function index(Request $request)
    {
        $this->validate($request, [
            'type' => 'required|string',
            'id' => 'required|integer',
        ]);

        $id = $request->id;
        $type = $request->type;

        if ($request->type == 'article') {
            $request->merge(['commentable_type' => Article::class]);
        }

        $query = Comment::where('comments.commentable_type', $request->commentable_type)
            ->where('comments.commentable_id', $id);

        if ($type == 'article') {
            // if type is article, ensure article is published and user is not hidden by article owner
            $query->whereHas('commentable', function ($query) use ($id) {
                $query->published()
                    ->where('id', $id)
                    ->whereDoesntHave('hiddenUsers', function ($query) {
                        $query->where('user_id', auth()->id());
                    });
            })->where('comments.parent_id', null);
        }

        $this->buildQuery($query, $request);

        // with replies paginated and sorted latest first
        // with replies count
        $query->with('user')
            ->with(['replies' => function ($query) {
                $query->latest();
            }])
            ->with('replies.user', 'likes')
            ->withCount('replies', 'likes')
            ->published();

        // get my blocked users
        $myBlockedUserIds = auth()->user()->usersBlocked()->pluck('blockable_id')->toArray();
        $peopleWhoBlockedMeIds = auth()->user()->blockedBy()->pluck('user_id')->toArray();

        // filter out my blocked users ids comments and its replies
        $query->whereNotIn('comments.user_id', array_unique(array_merge($myBlockedUserIds, $peopleWhoBlockedMeIds)));

        // hide users that is not deleted or not active
        $query->whereHas('user', function ($query) {
            $query->where('status', User::STATUS_ACTIVE);
        });

        // filter out if replies parent user_id is someone i blocked
        // $query->whereDoesntHave('replies.user.usersBlocked', function ($query) {
        //     $query->where('user_id', auth()->id())
        //         ->orWhere('blockable_id', auth()->id());
        // });

        $data = $query->paginate(config('app.paginate_per_page'));

        // post process replies
        // TODO: enhance this as the primary query will still call all replies
        if ($data && request()->has('replies_per_comment')) {
            // go to each comment and limit the replies to replies_per_comment (default: 3)
            $replies_per_comment = $request->replies_per_comment ? $request->replies_per_comment : 3;
            $data->map(function ($item, $key) use ($replies_per_comment) {
                $item->replies = $item->replies->take($replies_per_comment);
            });
        }
        return CommentResource::collection($data);
    }

    /**
     * Create a new comment by logged in user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     *
     * @group Article
     * @subgroup Comments
     * @authenticated
     * @bodyParam parent_id integer The id of the parent comment (For Replies). Example: 1
     * @bodyParam type string required The type of commentable. Example: article
     * @bodyParam id integer required The id of the commentable. Example: 1
     * @bodyParam body string required The body of the comment. Example: This is a comment
     * @bodyParam tagged_users array List of user ids tagged in comment. Example: [1, 2, 3]
     *
     * @response scenario=success {
     *  "comment": {},
     * }
     *
     * @response status=404 scenario="Not Found"
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["commentable_type": ["The Commentable Type field is required."] ]}
     */
    public function store(CreateCommentRequest $request)
    {
        if ($request->type == 'article') {
            $request->merge(['type' => Article::class]);
        }

        // request has parent_id, check if parent comment exists
        $parent = null;
        if ($request->has('parent_id') && $request->parent_id) {
            $parent = Comment::where('id', $request->parent_id)
                ->where('commentable_type', $request->type)
                ->where('commentable_id', $request->id)
                ->first();
        }

        // TODO: auto filter comment through spam filter

        $comment = Comment::create([
            'user_id' => auth()->id(),
            'commentable_type' => $request->type,
            'commentable_id' => $request->id,
            'body' => $request->body,
            'parent_id' => $parent ? $parent->id : null,
            'status' => Comment::STATUS_PUBLISHED, // DEFAULT ALL PUBLISHED
        ]);

        try {
            if ($request->has('tagged_users')) {
                $comment->taggedUsers()->sync($request->tagged_users);

                // notifiy tagged user
                $comment->taggedUsers->each(function ($taggedUser) use ($comment) {
                    try {
                        $locale = $taggedUser->last_lang ?? config('app.locale');
                        $taggedUser->notify((new TaggedUserInComment($comment, $comment->user))->locale($locale));
                    } catch (\Exception $e) {
                        Log::error('[CommentController] Notification error when tagged user', ['message' => $e->getMessage(), 'user' => $taggedUser]);
                    }
                });
            }
        } catch (\Exception $e) {
            Log::error('[CommentController] Tagged user error', ['message' => $e->getMessage()]);
        }

        event(new \App\Events\CommentCreated($comment)); // fires event

        try {
            if ($comment && $comment->parent_id && $comment->parent->user->id !== auth()->user()->id) {
                $locale = $comment->parent->user->last_lang ?? config('app.locale');
                // if comment has parent and is not self, send notification to parent comment's user
                $comment->parent->user->notify((new \App\Notifications\CommentReplied($comment))->locale($locale)); // send notification
            }
        } catch (\Exception $e) {
            Log::error('[CommentController] Notification error when parent comment', ['message' => $e->getMessage()]);
        }

        // if commentable has user and is not self, send notification
        try {
            if ($comment->commentable->user && $comment->commentable->user->id != auth()->id()) {
                $locale = $comment->commentable->user->last_lang ?? config('app.locale');
                $comment->commentable->user->notify((new \App\Notifications\Commented($comment))->locale($locale)); // send notification
            }
        } catch (\Exception $e) {
            Log::error('[CommentController] Notification error when commentable user', ['message' => $e->getMessage()]);
        }

        return response()->json([
            'comment' => CommentResource::make($comment),
            'article_id' => $comment->commentable_id,
        ]);
    }

    /**
     * Show one comment by ID
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     *
     * @group Article
     * @subgroup Comments
     * @authenticated
     * @bodyParam id integer required The id of the comment. Example: 1
     * @bodyParam replies_per_comment integer Number of replies to show per comment. Example: 3
     * @response scenario=success {
     *  "comment": {},
     * }
     * @response status=404 scenario="Not Found"
     * @response status=401 scenario="Forbidden"
     */
    public function show($id, Request $request)
    {
        $comment = Comment::where('id', $id)->with('user')
            ->with(['replies' => function ($query) {
                $query->latest();
            }])
            ->with('replies.user')
            ->withCount('replies')
            ->firstOrFail();

        if ($comment && request()->has('replies_per_comment')) {
            // go to each comment and limit the replies to replies_per_comment (default: 3)
            $replies_per_comment = $request->replies_per_comment ? $request->replies_per_comment : 3;
            $comment->map(function ($item, $key) use ($replies_per_comment) {
                $item->replies = $item->replies->take($replies_per_comment);
            });
        }

        // TODO: check if user is blocked to view comment

        return response()->json([
            'comment' => CommentResource::make($comment),
        ]);
    }

    /**
     * Update comment by ID. (Only owner of comment can update)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     *
     * @group Article
     * @subgroup Comments
     * @authenticated
     * @urlParam id integer required The id of the comment. Example: 1
     * @bodyParam body string required The body of the comment. Example: This is a comment
     * @bodyParam tagged_users array List of user ids tagged in comment. Example: [1, 2, 3]
     *
     * @response scenario=success {
     * "message": "Comment updated",
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["body": ["The Body field is required."] ]}
     * @response status=404 scenario="Not Found" {"message": "Comment not found"}
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'body' => 'required|string',
        ]);

        // check if owner of comment
        $comment = Comment::where('id', $id)->where('user_id', auth()->id());

        if ($comment->exists()) {
            $comment->update($request->only(['body']));

            // update tagged_users as well
            if ($request->has('tagged_users')) {
                // old list
                $oldTaggedUsers = $comment->taggedUsers->pluck('id')->toArray();

                $comment->taggedUsers()->sync($request->tagged_users);

                // send notification to newly tagged user
                $newTaggedUsers = array_diff($request->tagged_users, $oldTaggedUsers);

                $comment->taggedUsers->whereIn('id', $newTaggedUsers)->each(function ($taggedUser) use ($comment) {
                    try {
                        $taggedUser->notify(new TaggedUserInComment($comment, $comment->user));
                    } catch (\Exception $e) {
                        Log::error('[CommentController] Notification error when tagged user', ['message' => $e->getMessage(), 'user' => $taggedUser]);
                    }
                });
            }
            return response()->json(['message' => __('messages.success.comment_controller.Comment_updated')]);
        } else {
            return response()->json(['message' => __('messages.error.comment_controller.Comment_not_found')], 404);
        }
    }

    /**
     * Remove comment by ID. (Only owner of comment can delete)
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     *
     * @group Article
     * @subgroup Comments
     * @authenticated
     * @urlParam id integer required The id of the comment. Example: 1
     */
    public function destroy($id)
    {
        // check if owner of comment
        $comment = Comment::where('id', $id)->where('user_id', auth()->id());
        if ($comment->exists()) {
            // unlink any interactions on comment
            $comment->first()->likes()->delete();

            // delete replies to comment if comment has parent_id of this comment
            $comment->first()->replies()->delete();

            $comment->delete();
            return response()->json(['message' => __('messages.success.comment_controller.Comment_deleted')]);
        } else {
            return response()->json(['message' => __('messages.error.comment_controller.Comment_not_found')], 404);
        }
    }

    /**
     * Report a comment
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     *
     * @group Article
     * @subgroup Comments
     * @bodyParam comment_id integer required The id of the comment. Example: 1
     * @bodyParam reason string required The reason for reporting the comment. Example: Spam
     * @bodyParam violation_type required The violation type of this report
     * @bodyParam violation_level required The violation level of this report
     * @response scenario=success {
     * "message": "Comment reported",
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["comment_id": ["The Comment Id field is required."] ]}
     * @response status=422 scenario="Invalid Form Fields" {"message": "You have already reported this comment" ]}
     */
    public function postReportComment(Request $request)
    {
        $request->validate([
            'comment_id' => 'required|integer',
            'reason' => 'required|string',
            'violation_level' => 'required|integer',
            'violation_type' => 'required|string'
        ]);
        $comment = Comment::where('id', request('comment_id'))->firstOrFail();

        // check if user has reported this comment before if not create
        if (!$comment->reports()->where('user_id', auth()->id())->exists()) {
            $comment->reports()->create([
                'user_id' => auth()->id(),
                'reason' => request('reason'),
                'violation_level' => request('violation_level'),
                'violation_type' => request('violation_type'),
            ]);

            // TODO: Auto hide comment if comment is reported more than X times
            event(new \App\Events\CommentReported($comment)); // fires event

        } else {
            return response()->json(['message' => __('messages.error.comment_controller.You_have_already_reported_this_comment')], 422);
        }

        return response()->json(['message' => __('messages.success.comment_controller.Comment_reported')]);
    }

    /**
     * Get replies to a comment
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     *
     * @group Article
     * @subgroup Comments
     * @bodyParam filter string Column to Filter. Example: Filterable columns are: id, commentable_id, commentable_type, body, created_at, updated_at
     * @bodyParam filter_value string Value to Filter. Example: Filterable values are: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10
     * @bodyParam sort string Column to Sort. Example: Sortable columns are: id, commentable_id, commentable_type, body, created_at, updated_at
     * @bodyParam order string Direction to Sort. Example: Sortable directions are: asc, desc
     * @bodyParam limit integer Per Page Limit Override. Example: 10
     * @bodyParam offset integer Offset Override. Example: 0
     * @response scenario=success {
     * "data": {},
     * }
     * @response status=404 scenario="Not Found" {"message": "Comment not found"}
     */
    public function getRepliesByCommentId(Request $request, $id)
    {
        $comment = Comment::where('id', $id)->firstOrFail();

        $query = $comment->replies()->latest()
            ->withCount('replies');

        $this->buildQuery($query, $request);

        $data = $query->with('user');

        $data = $query->paginate(config('app.paginate_per_page'));

        return CommentResource::collection($data);
    }

    /**
     * Toggle a Comment Like
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     *
     * @group Article
     * @subgroup Comments
     * @bodyParam comment_id integer required The id of the comment. Example: 1
     * @response scenario=success {
     * "message": "Comment liked/Un-Liked",
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["comment_id": ["The Comment Id field is required."] ]}
     */
    public function postLikeToggle(Request $request)
    {
        $request->validate([
            'comment_id' => 'required|integer',
        ]);

        $comment = Comment::where('id', request('comment_id'))->firstOrFail();

        // check if user has liked this comment before if not create
        if (!$comment->likes()->where('user_id', auth()->id())->exists()) {
            $comment->likes()->create([
                'user_id' => auth()->id(),
            ]);
            event(new \App\Events\CommentLiked($comment, true)); // fires event

            if ($comment && $comment->user && $comment->user->id != auth()->id()) {
                $locale = $comment->user->last_lang ?? config('app.locale');
                // send notification to user
                $comment->user->notify((new \App\Notifications\CommentLiked($comment, auth()->user()))->locale($locale));
            }

            return response()->json(['message' => __('messages.success.comment_controller.Comment_liked')]);
        } else {
            // unlike
            $comment->likes()->where('user_id', auth()->id())->delete();
            event(new \App\Events\CommentLiked($comment, false)); // fires event
            return response()->json(['message' => __('messages.success.comment_controller.Comment_Un-Liked')]);
        }
    }

    /**
     * Get taggable users in comment
     * Only users whos is followers of logged in user can be tag in article
     *
     * @group Article
     * @subgroup Comments
     * @response scenario=success {
     * "data": [],
     * }
     *
     *
     * @return JsonResource
     */
    public function getTaggableUsersInComment()
    {
        // get my followers
        $myFollowers = auth()->user()->followers->pluck('id')->toArray();

        $taggableUsers = User::where('id', '!=', auth()->id())
            ->where('status', User::STATUS_ACTIVE)
            ->whereIn('id', $myFollowers)
            ->paginate(config('app.paginate_per_page'));

        return UserResource::collection($taggableUsers);
    }

    /**
     * Untag user in comment
     *
     * @group Article
     * @subgroup Comments
     * @bodyParam comment_id integer required The id of the comment. Example: 1
     *
     * @response scenario=success {
     * "message": "User untagged from comment",
     * "comment": {},
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function postUntagUserFromComment(Request $request)
    {
        $request->validate([
            'comment_id' => 'required|integer'
        ]);

        // get comment
        $comment = Comment::where('id', request('comment_id'))->firstOrFail();

        // untag user
        $comment->taggedUsers()->detach(auth()->id());

        // refresh comment
        $comment->refresh();

        return response()->json([
            'message' => __('messages.success.comment_controller.User_untagged_from_comment'),
            'comment' => CommentResource::make($comment),
        ]);
    }
}
