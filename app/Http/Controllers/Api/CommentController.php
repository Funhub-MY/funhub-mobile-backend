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
     * @urlParam type string required The type of commentable. Example: article
     * @urlParam id integer required The id of the commentable. Example: 1
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
    public function index($type, $id, Request $request)
    {
        if ($request->type == 'article') {
            $request->merge(['commentable_type' => Article::class]);
        }

        $query = Comment::where('commentable_type', $request->commentable_type)
            ->where('commentable_id', $id);

        if ($type == 'article') {
            // if type is article, ensure article is published and user is not hidden by article owner
            $query->whereHas('article', function ($query) {
                $query->published()
                    ->whereDoesntHave('hiddenUsers', function ($query) {
                        $query->where('user_id', auth()->id());
                    });
            });
        }

        $this->buildQuery($query, $request);
        $data = $query->with('user')->paginate(config('app.paginate_per_page'));
        return response()->json(CommentResource::collection($data));
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
     * @bodyParam type string required The type of commentable. Example: article
     * @bodyParam id integer required The id of the commentable. Example: 1
     * @bodyParam body string required The body of the comment. Example: This is a comment
     * 
     * @response scenario=success {
     *  "comment": {},
     * }
     * 
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["commentable_type": ["The Commentable Type field is required."] ]}
     */
    public function store(CreateCommentRequest $request)
    {
        if ($request->type == 'article') {
            $request->merge(['type' => Article::class]);
        }

        // TODO: auto filter comment through spam filter

        $comment = Comment::create([
            'user_id' => auth()->id(),
            'commentable_type' => $request->type,
            'commentable_id' => $request->id,
            'body' => $request->body,
        ]);

        event(new \App\Events\CommentCreated($comment)); // fires event

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
     * @response scenario=success {
     *  "comment": {},
     * } 
     * @response status=404 scenario="Not Found"
     * @response status=401 scenario="Forbidden"
     */
    public function show($id)
    {
        $comment = Comment::where('id', $id)->with('user')
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
     * @response scenario=success {
     * "message": "Comment reported",
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["comment_id": ["The Comment Id field is required."] ]}
     */
    public function postReportComment(Request $request)
    {
        $request->validate([
            'comment_id' => 'required|integer',
            'reason' => 'required|string',
        ]);
        $comment = Comment::where('id', request('comment_id'))->firstOrFail();
        $comment->reports()->create([
            'user_id' => auth()->id(),
            'reason' => request('reason'),
        ]);

        // TODO: Auto hide comment if comment is reported more than X times
        event(new \App\Events\CommentReported($comment)); // fires event

        return response()->json(['message' => 'Comment reported']);
    }
}
