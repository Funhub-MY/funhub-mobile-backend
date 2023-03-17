<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleTagResource;
use App\Models\ArticleTag;
use Illuminate\Http\Request;

class ArticleTagController extends Controller
{
    /**
     * Get popular tags
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group Article
     * @subgroup Article Tags
     * @bodyParam limit integer optional Limit the number of tags returned. Example: 10
     * @bodyParam offset integer optional Offset the number of tags returned. Example: 10
     * @bodyParam query integer optional Search for a category by name. Example: "#food"

     * @response scenario=success {
     * "tags": []
     * }
     * 
     * @response status=404 scenario="Not Found"
     */
    public function index(Request $request)
    {
        // get popular tags by article count
        $query = ArticleTag::withCount('articles')
            ->orderBy('articles_count', 'desc');
            
        if ($request->has('limit')) {
            $query->limit($request->limit);
        } else {
            $query->limit(10);
        }

        if ($request->has('offset')) {
            $query->offset($request->offset);
        }

        if ($request->has('query')) {
            $query->where('name', 'like', '%' . $request->query . '%');
        }

        $tags = $query->get();

        return response()->json([
            'tags' => ArticleTagResource::collection($tags)
        ]);
    }

    /**
     * Get tags by article id
     * 
     * @param $article_id integer
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group Article
     * @subgroup Article Tags
     * @urlParam article_id integer required The id of the article. Example: 1
     * 
     * @response scenario=success {
     * "tags": []
     *  }
     * 
     * @response status=404 scenario="No tags found"
     */
    public function getTagByArticleId($article_id)
    {
        // get a list of article tags belonging to an article
        $tags = ArticleTag::where('article_id', $article_id)
            ->firstOrFail();

        return response()->json([
            'tags' => ArticleTagResource::collection($tags)
        ]);
    }
}
