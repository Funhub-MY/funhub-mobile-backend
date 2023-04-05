<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleCategoryResource;
use App\Models\ArticleCategory;
use App\Traits\QueryBuilderTrait;
use Illuminate\Http\Request;

class ArticleCategoryController extends Controller
{
    use QueryBuilderTrait;

    /**
     * Get popular Article Categories
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group Article
     * @subgroup Article Categories
     * @bodyParam filter string Column to Filter. Example: Filterable columns are: id, name, created_at, updated_at
     * @bodyParam filter_value string Value to Filter. Example: Filterable values are: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10
     * @bodyParam sort string Column to Sort. Example: Sortable columns are: id, name, created_at, updated_at
     * @bodyParam order string Direction to Sort. Example: Sortable directions are: asc, desc
     * @bodyParam limit integer Per Page Limit Override. Example: 10
     * @bodyParam offset integer Offset Override. Example: 0
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

        $this->buildQuery($query, $request);

        $categories = $query->paginate(config('app.paginate_per_page'));

        return ArticleCategoryResource::collection($categories);
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
        $article = ArticleCategory::whereHas('articles', function ($query) use ($article_id) {
            $query->where('article_id', $article_id);
        })->paginate(config('app.paginate_per_page'));
        
        return ArticleCategoryResource::collection($article);
    }
}
