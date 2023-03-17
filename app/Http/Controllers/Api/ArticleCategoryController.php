<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleCategoryResource;
use App\Models\ArticleCategory;
use Illuminate\Http\Request;

class ArticleCategoryController extends Controller
{
    /**
     * Get popular Article Categories
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group Article
     * @subgroup Article Categories
     * @bodyParam limit integer optional Limit the number of tags returned. Example: 10
     * @bodyParam offset integer optional Offset the number of tags returned. Example: 10
     * @bodyParam query integer optional Search for a category by name. Example: "Entertainment"
     * @response scenario=success {
     * "categories": []
     * }
     * @response status=404 scenario="Not Found"
     */
    public function index(Request $request)
    {
         // get popular tags by article count
         $query = ArticleCategory::withCount('articles')
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
            'categories' => ArticleCategoryResource::collection($tags)
        ]);
    }

    /**
     * Get Article Categories by article id
     * 
     * @param $article_id integer
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group Article
     * @subgroup Article Categories
     * @urlParam article_id integer required The id of the article. Example: 1
     * @response scenario=success {
     * "categories": []
     * }
     * @response status=404 scenario="Not Found"
     */
    public function getArticleCategoryByArticleId($article_id)
    {
        $article = ArticleCategory::where('article_id', $article_id)->firstOrFail();

        return response()->json([
            'categories' => ArticleCategoryResource::collection($article)
        ]);
    }
}
