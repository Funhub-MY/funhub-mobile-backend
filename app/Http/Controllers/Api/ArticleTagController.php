<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleTagResource;
use App\Models\ArticleTag;
use App\Traits\QueryBuilderTrait;
use Illuminate\Http\Request;

class ArticleTagController extends Controller
{
    use QueryBuilderTrait;

    /**
     * Get popular tags
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Article
     * @subgroup Article Tags
     * @bodyParam filter string Column to Filter. Example: Filterable columns are: id, name, created_at, updated_at
     * @bodyParam filter_value string Value to Filter. Example: Filterable values are: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10
     * @bodyParam sort string Column to Sort. Example: Sortable columns are: id, name, created_at, updated_at
     * @bodyParam order string Direction to Sort. Example: Sortable directions are: asc, desc
     * @bodyParam limit integer Per Page Limit Override. Example: 10
     * @bodyParam offset integer Offset Override. Example: 0
     *
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

        $this->buildQuery($query, $request);

        $tags = $query->paginate(config('app.paginate_per_page'));

        return ArticleTagResource::collection($tags);
    }

    /**
     * Get all tags available
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Article
     * @subgroup Article Tags
     * @bodyParam filter string Column to Filter. Example: Filterable columns are: id, name, created_at, updated_at
     * @bodyParam filter_value string Value to Filter. Example: Filterable values are: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10
     * @bodyParam sort string Column to Sort. Example: Sortable columns are: id, name, created_at, updated_at
     * @bodyParam order string Direction to Sort. Example: Sortable directions are: asc, desc
     * @bodyParam limit integer Per Page Limit Override. Example: 10
     *
     * @response scenario=success {
     * "tags": []
     * }
     */
    public function getAllTags(Request $request)
    {
        // get all tags
        $query = ArticleTag::query();

        $this->buildQuery($query, $request);

        $tags = $query->paginate(config('app.paginate_per_page'));

        return ArticleTagResource::collection($tags);
    }

    /**
     * Get Tags by article id
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
        $tags = ArticleTag::whereHas('articles', function ($query) use ($article_id) {
            $query->where('article_id', $article_id);
        })->paginate(config('app.paginate_per_page'));

        return  ArticleTagResource::collection($tags);
    }
}
