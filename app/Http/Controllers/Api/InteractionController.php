<?php

namespace App\Http\Controllers\Api;

use App\Events\InteractionCreated;
use App\Http\Controllers\Controller;
use App\Http\Resources\InteractionResource;
use App\Http\Resources\UserResource;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Interaction;
use App\Models\MerchantOffer;
use App\Models\ShareableLink;
use App\Models\User;
use App\Notifications\ArticleInteracted;
use App\Traits\QueryBuilderTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InteractionController extends Controller
{
    use QueryBuilderTrait;

    /**
     * Get interactions on a interactable type (eg. Articles)
     *
     * @param $type string
     * @param $id integer
     * @param Request $request
     * @return \Illuminate\Http\Response
     *
     * @group Interactions
     * @authenticated
     * @bodyParam interactable string required The type of interactable. Example: article,merchant_offer
     * @bodyParam id integer required The id of the interactable. Example: 1
     * @bodyParam filter string Column to Filter. Example: Filterable columns are: id, interactable_id, interactable_type, body, created_at, updated_at
     * @bodyParam filter_value string Value to Filter. Example: Filterable values are: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10
     * @bodyParam sort string Column to Sort. Example: Sortable columns are: id, interactable_id, interactable_type, body, created_at, updated_at
     * @bodyParam order string Direction to Sort. Example: Sortable directions are: asc, desc
     * @bodyParam limit integer Per Page Limit Override. Example: 10
     * @bodyParam offset integer Offset Override. Example: 0
     * @response scenario=success {
     *  "data": [],
     *  "links": {},
     *  "meta": {
     *     "current_page": 1,
     *   }
     * }
     * @response status=404 scenario="Not Found"
    */
    public function index(Request $request)
    {
        $this->validate($request, [
            'interactable' => 'required|string',
            'id' => 'required|integer',
        ]);

        $id = $request->id;
        $interactable = $request->interactable;

        // get all interactions of a interactable type
        if ($request->interactable == 'article') {
            $request->merge(['interactable_type' => Article::class]);
        }

        if ($request->interactable == 'merchant_offer') {
            $request->merge(['interactable_type' => MerchantOffer::class]);
        }

        $query = Interaction::where('interactable_type', $request->interactable_type)
            ->where('interactable_id', $id);

        if ($interactable == 'article') {
            // if type is article, ensure article is published and user is not hidden by article owner
            $query->whereHas('interactable', function ($query) {
                $query->published()
                    ->whereDoesntHave('hiddenUsers', function ($query) {
                        $query->where('user_id', auth()->id());
                    });
            });
        }

        $this->buildQuery($query, $request);
        $data = $query->with('user')
            ->published()
            ->paginate(config('app.paginate_per_page'));

        return InteractionResource::collection($data);
    }

    /**
     * Create an interaction for interactable type
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     *
     * @group Interactions
     * @authenticated
     * @bodyParam interactable string required The type of interactable. Example: article,merchant_offer
     * @bodyParam type string required The type of interaction. Example: like,dislike,share,bookmark
     * @bodyParam id integer required The id of the interactable (eg. Article ID). Example: 1
     * @bodyParam code string optional The code of the shareable link(6 characters). Example: 1
     * @bodyParam model_type string optional The model type of the shareable link. Example: article,merchant_offer
     * @response scenario=success {
     *  "interaction": {}
     * }
     * @response 422
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'interactable' => 'required|string',
            'type' => 'required|string|in:like,dislike,share,bookmark',
            'id' => 'required|integer',
        ]);

        if ($request->interactable == 'article') {
            $request->merge(['interactable' => Article::class]);
        }

        if ($request->interactable == 'merchant_offer') {
            $request->merge(['interactable' => MerchantOffer::class]);
        }

        switch($request->type) {
            case 'like':
                $request->merge(['type' => Interaction::TYPE_LIKE]);
                break;
            case 'dislike':
                $request->merge(['type' => Interaction::TYPE_DISLIKE]);
                break;
            case 'share':
                $request->merge(['type' => Interaction::TYPE_SHARE]);
                break;
            case 'bookmark':
                $request->merge(['type' => Interaction::TYPE_BOOKMARK]);
                break;
        }

        // all interactions should be unique to user, interactable_type, interactable_id, type, use firstOrCreate
        $interaction = Interaction::firstOrCreate([
            'user_id' => auth()->id(),
            'interactable_type' => $request->interactable,
            'interactable_id' => $request->id,
            'type' => $request->type,
        ]);

        // if interaction type is share, create ShareableLink then link it to interaction
        if ($request->type == Interaction::TYPE_SHARE) {
            // get sharable link code and model type and id from frontend
            $this->validate($request, [
                'code' => 'required|string|min:6|max:6',
                'model_type' => 'required|string|in:article,merchant_offer',
            ]);

            // check if code already generated before
            $shareableLink = ShareableLink::where('link', $request->code)
                ->first();
            if ($shareableLink) {
                // reject
                return response()->json([
                    'message' => 'Shareable link already exists. provide new code.',
                ], 422);
            }

            // model type
            if ($request->model_type == 'article') {
                $request->merge(['model_type' => Article::class]);
            } else if ($request->model_type == 'merchant_offer') {
                $request->merge(['model_type' => MerchantOffer::class]);
            }

            // create new shareable link exists for this article and user
            $shareableLink = ShareableLink::create([
                'link' => $request->code, // random 6 characters
                'user_id' => auth()->id(), // logged in user
                'model_id' => $request->id, // eg Article Id
                'model_type' => $request->model_type, // eg Article Model Type
            ]);

            // link to interaction via relationship ShareableLink
            $interaction->shareableLink()->attach($shareableLink->id);
        }

        event(new InteractionCreated($interaction));

        // notify user of interactable is article and is like
        if ($request->interactable == Article::class && $request->type == Interaction::TYPE_LIKE && $interaction->interactable->user->id != auth()->id()) {
            $interaction->interactable->user->notify(new ArticleInteracted($interaction));
        }

        return response()->json([
            'interaction' => new InteractionResource($interaction),
        ]);
    }

    /**
     * Show one interaction by ID
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     *
     * @group Interactions
     * @authenticated
     * @urlParam id integer required The id of the interaction. Example: 1
     */
    public function show($id)
    {
        $interaction = Interaction::where('id', $id)->with('user')
            ->firstOrFail();

        return response()->json([
            'interaction' => new InteractionResource($interaction),
        ]);
    }

    /**
     * Remove Interaction By ID
     * Only owner can call this method
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     *
     * @group Interactions
     * @authenticated
     * @urlParam id integer required The id of the interaction. Example: 1
     * @response scenario=success {
     * "message": "Interaction deleted"
     * }
     *
     * @response status=404 scenario="Not Found" {['message' => 'Interaction not found']}
     */
    public function destroy($id)
    {
        $interaction = Interaction::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if ($interaction->exists()) {
            $interaction->delete();
            return response()->json(['message' => 'Interaction deleted']);
        } else {
            return response()->json(['message' => 'Interaction not found'], 404);
        }
    }

    /**
     * Get Users of Interaction
     *
     * @param Request $request
     * @return UserResource
     *
     * @group Interactions
     * @authenticated
     * @bodyParam interactable string required The type of interactable. Example: article,merchant_offer
     * @bodyParam id integer required The id of the interactable. Example: 1
     * @bodyParam type string required The type of interaction. Example: like,dislike,share,bookmark
     *
     * @response scenario=success {
     * "data": [],
     * "links": {},
     * "meta": {
     * }
     * }
     */
    public function getUsersOfInteraction(Request $request)
    {
        $this->validate($request, [
            'interactable' => 'required|string',
            'id' => 'required|integer',
            'type' => 'required|string|in:like,dislike,share,bookmark',
        ]);

        if ($request->interactable == 'article') {
            $request->merge(['interactable' => Article::class]);
        }
        if ($request->interactable == 'merchant_offer') {
            $request->merge(['interactable' => MerchantOffer::class]);
        }
        if ($request->interactable == 'comment') {
            $request->merge(['interactable' => Comment::class]);
        }

        switch($request->type) {
            case 'like':
                $request->merge(['type' => Interaction::TYPE_LIKE]);
                break;
            case 'dislike':
                $request->merge(['type' => Interaction::TYPE_DISLIKE]);
                break;
            case 'share':
                $request->merge(['type' => Interaction::TYPE_SHARE]);
                break;
            case 'bookmark':
                $request->merge(['type' => Interaction::TYPE_BOOKMARK]);
                break;
        }

        $users = User::whereHas('interactions', function ($query) use ($request) {
            $query->where('interactable_type', $request->interactable)
                ->where('interactable_id', $request->id)
                ->where('type', $request->type);
        })->paginate(config('app.paginate_per_page'));

        return UserResource::collection($users);
    }
}
