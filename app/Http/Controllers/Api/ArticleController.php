<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArticleCreateRequest;
use App\Http\Requests\ArticleImagesUploadRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\ArticleTag;
use App\Traits\QueryBuilderTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use function PHPSTORM_META\map;

class ArticleController extends Controller
{
    use QueryBuilderTrait;

    /**
     * Get Articles for Logged in user
     * 
     * @group Article
     * @bodyParam filter string Column to Filter. Example: Filterable columns are: id, title, type, slug, status, published_at, created_at, updated_at
     * @bodyParam filter_value string Value to Filter. Example: Filterable values are: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10
     * @bodyParam sort string Column to Sort. Example: Sortable columns are: id, title, type, slug, status, published_at, created_at, updated_at
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
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // TODO: get article for home page based on user preferences

        $query = Article::published()
            ->whereDoesntHave('hiddenUsers', function ($query) {
                $query->where('user_id', auth()->user()->id);
            });

        $this->buildQuery($query, $request);

        $data = $query->with('user', 'comments', 'interactions', 'media', 'categories', 'tags')
            ->paginate(config('app.paginate_per_page'));

        return ArticleResource::collection($data);
    }

    /**
     * Create New Article
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @group Article
     * @bodyParam title string required The title of the article. Example: This is a title
     * @bodyParam type string required The type of the article. Example: multimedia,text,video
     * @bodyParam body string required The body of the article. Example: This is a caption or body of article
     * @bodyParam status integer required The status of the article. Example: 0 is Draft,1 is Published
     * @bodyParam published_at datetime The published date of the article. Example: 2021-02-21 12:00:00
     * @bodyParam tags array The tags of the article. Example: ["#tag1", "#tag2"]
     * @bodyParam categories array The categories ID of the article. Example: [1, 2]
     * @bodyParam images file The images ID of the article. Must first call upload images endpoint. Example: [1, 2]
     * @bodyParam excerpt string The excerpt of the article. Example: This is a excerpt of article
     * 
     * 
     * @response scenario=success {
     * "message": "Article updated",
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["body": ["The Body field is required."] ]}
     * @response status=404 scenario="Not Found" {"message": "Comment not found"}
     */
    public function store(ArticleCreateRequest $request)
    {
        $user = auth()->user;

        $article = Article::create([
            'title' => $request->title,
            'type' => $request->type,
            'slug' => Str::slug($request->title),
            'excerpt' => ($request->excerpt) ?? null,
            'body' => $request->body,
            'status' => $request->status, // default is dfraft
            'published_at' => ($request->published_at) ? Carbon::parse($request->published_at)->toDateTimeString() : null,
            'user_id' => $user->id,
        ]);

        // if request->images attach images from user_uploads to article_images collection media library
        if ($request->has('images')) {
            $article->addMediaFromRequest('images')
                ->toMediaCollection('article_images');
        }

        // attach categories, do not create new categories if not exist
        if ($request->has('categories')) {
            // categories must be array of ids of article categories
            $article->categories()->attach($request->categories);
        }

        // attach tags, if doesn't exist create new with # as prefix
        if ($request->has('tags')) {
            $tags = $request->tags;
            $tags = array_map(function ($tag) {
                return Str::startsWith($tag, '#') ? $tag : '#' . $tag;
            }, $tags);

            // create or update tags
            $tags = collect($tags)->map(function ($tag) {
                return ArticleTag::firstOrCreate(['name' => $tag])->id;
            });
            $article->tags()->attach($tags);
        }

        return response()->json([
            'message' => 'Article created',
            'article' => new ArticleResource($article),
        ]);
    }

    /**
     * Get One Article by ID
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @group Article
     * @urlParam id integer required The id of the article. Example: 1
     * @response scenario=success {
     *  "article": {}
     * }
     * @response status=404 scenario="Not Found" {"message": "Article not found"}
     */
    public function show($id)
    {
        $article = Article::with('user', 'comments', 'interactions', 'media', 'categories', 'tags')
            ->where('id', $id)
            ->whereDosentHave('hiddenUsers', function ($query) {
                $query->where('user_id', auth()->user()->id);
            })
            ->published()
            ->first();

        if (!$article) {
            return response()->json([ 'message' => 'Article not found'], 404);
        }

        return response()->json([
            'article' => new ArticleResource($article)
        ]);
    }

    /**
     * Update article by ID. (Only owner of article can update)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @group Article
     * @urlParam id integer required The id of the article. Example: 1
     * @bodyParam body string required The body of the article. Example: This is a comment
     * @bodyParam status integer required The status of the article, change this to 0 to unpublish. Example: 0 is Draft,1 is Published
     * @bodyParam tags array The tags of the article. Example: ["#tag1", "#tag2"]
     * @bodyParam categories array The categories ID of the article. Example: [1, 2]
     * @bodyParam images file The images ID of the article.
     * 
     * @response scenario=success {
     * "message": "Article updated",
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["body": ["The Body field is required."] ]}
     * @response status=404 scenario="Not Found" {"message": "Comment not found"}
     */
    public function update(UpdateArticleRequest $request, $id)
    {
        // check if owner of Article
        $article = Article::where('id', $id)->where('user_id', auth()->id());
        if ($article->exists()) {
            
            $article->update([
                'body' => $request->body,
                'status' => $request->status,
            ]);

            // attach or detach images
            if ($request->has('images')) {
                $article->addMediaFromRequest('images')
                    ->toMediaCollection('article_images');
            }

            // detach and delete images no longer in request->images
            if ($request->has('images')) {
                $article->getMedia('article_images')->each(function ($media) use ($request) {
                    if (!in_array($media->id, $request->images)) {
                        $media->delete();
                    }
                });
            }

            // sync category
            if ($request->has('categories')) {
                $article->categories()->sync($request->categories);
            }

            // sync tags
            if ($request->has('tags')) {
                $tags = $request->tags;
                $tags = array_map(function ($tag) {
                    return Str::startsWith($tag, '#') ? $tag : '#' . $tag;
                }, $tags);

                // create or update tags
                $tags = collect($tags)->map(function ($tag) {
                    return ArticleTag::firstOrCreate(['name' => $tag])->id;
                });
                $article->tags()->sync($tags);
            }
            return response()->json(['message' => 'Article updated']);
        } else {
            return response()->json(['message' => 'Article not found'], 404);
        }
    }

    /**
     * Remove article by ID. (Only owner of article can delete)
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @group Article
     * @urlParam id integer required The id of the Article. Example: 1
     * @response scenario=success {
     * "message": "Article deleted"
     * }
     * @response status=404 scenario="Not Found" {"message": "Article not found"}
     */
    public function destroy($id)
    {
        // check if owner of Article
        $article = Article::where('id', $id)->where('user_id', auth()->id());
        if ($article->exists()) {
            $article->delete();
            return response()->json(['message' => 'Article deleted']);
        } else {
            return response()->json(['message' => 'Article not found'], 404);
        }
    }

    /**
     * Upload Images for Article
     * 
     * @group Article
     * @bodyParam images file required The images to upload. Example: [file1, file2]
     * @response scenario=success {
     * "uploaded": [
     *     {
     *        "id": 1,
     *        "name": "image.jpg",
     *        "url": "http://localhost:8000/storage/user_uploads/1/image.jpg",
     *        "thumbUrl": "http://localhost:8000/storage/user_uploads/1/thumb_image.jpg",
     *        "size": 12345,
     *        "type": "image/jpeg"
     *    }
     * ]
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["images": ["The images field is required."] ]}
     */
    public function postGalleryUpload(ArticleImagesUploadRequest $request)
    {
        $user = auth()->user;

        // upload via spatie medialibrary
        $uploaded = $user->addFromMediaLibraryRequest($request->images)
            ->each(function ($fileAdder) {
                $fileAdder->toMediaCollection('user_uploads');
            });
        
        return response()->json([
            'uploaded' => $uploaded->map(function ($file) {
                return [
                    'id' => $file->id,
                    'name' => $file->file_name,
                    'url' => $file->getUrl(),
                    'thumbUrl' => $file->getUrl('thumb'),
                    'size' => $file->size,
                    'type' => $file->mime_type,
                ];
            }),
        ]);
    }
}
