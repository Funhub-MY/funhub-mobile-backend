<?php

namespace App\Http\Controllers\Api;

use App\Events\InteractionCreated;
use App\Http\Controllers\Controller;
use App\Http\Resources\InteractionResource;
use App\Models\Article;
use App\Models\Interaction;
use App\Models\MerchantOffer;
use App\Models\ShareableLink;
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
            // create new shareable link exists for this article and user
            $shareableLink = ShareableLink::create([
                'link' => strtolower(Str::random(6)), // random 6 characters
                'user_id' => auth()->id(), // logged in user
                'model_id' => $request->id, // eg Article Id
                'model_type' => $request->interactable, // eg Article Model Type
            ]);

            // link to interaction via relationship ShareableLink
            $interaction->shareableLink()->attach($shareableLink->id);
        }

        event(new InteractionCreated($interaction));

        // notify user of interactable
        if ($request->interactable == Article::class) {
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
}
