<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Article;
use App\Models\Comment;
use App\Traits\QueryBuilderTrait;
use Illuminate\Http\Request;

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

        $query = Comment::where('commentable_type', $request->commentable_type)
            ->where('commentable_id', $id);

        // if ($type == 'article') {
        //     // if type is article, ensure article is published and user is not hidden by article owner
        //     $query->whereHas('commentable', function ($query) use ($id) {
        //         $query->published()
        //             ->where('id', $id)
        //             ->whereDoesntHave('hiddenUsers', function ($query) {
        //                 $query->where('user_id', auth()->id());
        //             });
        //     });
        // }

        // $this->buildQuery($query, $request);

        // with replies paginated and sorted latest first
        // with replies count
        $data = $query->with('user')
            ->with(['replies' => function ($query) use ($request) {
                $query->latest()
                    ->paginate($request->has('replies_per_comment') ? $request->replies_per_comment : 3);
            }])
            ->with('replies.user', 'likes')
            ->withCount('replies', 'likes')
            ->published()
            ->paginate(config('app.paginate_per_page'));

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

        event(new \App\Events\CommentCreated($comment)); // fires event

        if ($comment && $comment->parent_id && $comment->parent->user->id !== auth()->user()->id) {
            // if comment has parent and is not self, send notification to parent comment's user
            $comment->parent->user->notify(new \App\Notifications\CommentReplied($comment)); // send notification
        }

        // if commentable has user and is not self, send notification
        if ($comment->commentable->user && $comment->commentable->user->id != auth()->id()) {
            $comment->commentable->user->notify(new \App\Notifications\Commented($comment)); // send notification
        }

        return response()->json([
            'comment' => CommentResource::make($comment),
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
            ->with(['replies' => function ($query) use ($request) {
                $query->latest()
                    ->paginate($request->has('replies_per_comment') ? $request->replies_per_comment : 3);
            }])
            ->with('replies.user')
            ->withCount('replies')
            ->firstOrFail();

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
            return response()->json(['message' => 'Comment updated']);
        } else {
            return response()->json(['message' => 'Comment not found'], 404);
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
            return response()->json(['message' => 'Comment deleted']);
        } else {
            return response()->json(['message' => 'Comment not found'], 404);
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
            return response()->json(['message' => 'You have already reported this comment'], 422);
        }

        return response()->json(['message' => 'Comment reported']);
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
                // send notification to user
                $comment->user->notify(new \App\Notifications\CommentLiked($comment));
            }

            return response()->json(['message' => 'Comment liked']);
        } else {
            // unlike
            $comment->likes()->where('user_id', auth()->id())->delete();
            event(new \App\Events\CommentLiked($comment, false)); // fires event
            return response()->json(['message' => 'Comment Un-Liked']);
        }
    }
}
