<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArticleCreateRequest;
use App\Http\Requests\ArticleImagesUploadRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleTag;
use App\Models\Interaction;
use App\Models\User;
use App\Traits\QueryBuilderTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function PHPSTORM_META\map;

class ArticleController extends Controller
{
    use QueryBuilderTrait;

    /**
     * Get Articles for Logged in user (for Home Page)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Article
     *
     * @bodyParam article_ids array optional Article Ids to Filter. Example [1,2,3]
     * @bodyParam category_ids array optional Category Ids to Filter. Example: [1, 2, 3]
     * @bodyParam video_only integer optional Filter by Videos. Example: 1 or 0
     * @bodyParam following_only integer optional Filter by Articles by Users who logged in user is following. Example: 1 or 0
     * @bodyParam tag_ids array optional Tag Ids to Filter. Example: [1, 2, 3]
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
        $query = Article::published()
            ->whereDoesntHave('hiddenUsers', function ($query) {
                $query->where('user_id', auth()->user()->id);
            });

        // video only
        if ($request->has('video_only') && $request->video_only == 1) {
            $query->where('type', 'video');
        }

        // category_ids filter
        if ($request->has('category_ids')) {
            // explode categories ids
            $category_ids = explode(',', $request->category_ids);
            if (count($category_ids) > 0) {
                $query->whereHas('categories', function ($q) use ($category_ids) {
                    $q->whereIn('article_categories.id', $category_ids);
                });
            }
        }

        // article_ids filter
        if ($request->has('article_ids')) {
            // explode article ids
            $article_ids = explode(',', $request->article_ids);
            if (count($article_ids) > 0) {
                $query->whereIn('id', $article_ids);
            }
        }
        
        // tag_ids filter
        if ($request->has('tag_ids')) {
            $tag_ids = explode(',', $request->tag_ids);
            if (count($tag_ids) > 0) {
                $query->whereHas('tags', function ($q) use ($tag_ids) {
                    $q->whereIn('article_tags.id', $tag_ids);
                });
            }
        }

        // get articles from users whose this auth user is following only
        if($request->has('following_only') && $request->following_only == 1) {
            $myFollowings = auth()->user()->followings;

            $query->whereHas('user', function ($query) use ($myFollowings) {
                $query->whereIn('users.id', $myFollowings->pluck('id')->toArray());
            });
        }

        $this->buildQuery($query, $request);

        $data = $query->with('user', 'comments', 'interactions', 'media', 'categories', 'tags')
            ->withCount('comments', 'interactions', 'media', 'categories', 'tags')
            ->paginate(config('app.paginate_per_page'));

        return ArticleResource::collection($data);
    }

    /**
     * Get Articles for Logged in user (for Profile Page)
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     *
     * @group Article
     * @group Article
     * @bodyParam published_only boolean optional Filter by published articles. Example: true
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
     */
    public function getMyArticles(Request $request)
    {
        $query = Article::where('user_id', auth()->user()->id);

        if ($request->has('published_only')) {
            $query->where('status', Article::STATUS[1]);
        }

        $this->buildQuery($query, $request);

        $data = $query->with('user', 'comments', 'interactions', 'media', 'categories', 'tags')
            ->paginate(config('app.paginate_per_page'));

        return ArticleResource::collection($data);
    }

    /**
     * Get My Bookmarked Articles
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
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
     */
    public function getMyBookmarkedArticles(Request $request)
    {
        $query = Article::whereHas('interactions', function ($query) {
            $query->where('user_id', auth()->user()->id)
                ->where('type', Interaction::TYPE_BOOKMARK);
        })->published()
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
     * @bodyParam images array The images IDs. Must first call upload images endpoint. Example: [1, 2]
     * @bodyParam video integer The video ID. Must first call upload videos endpoint. Example: 1
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
        $user = auth()->user();

        // slug
        // regex detect if non-latin characters exists in $request->title
        $slug = '';
        if (preg_match('/[^\x20-\x7E]/', $request->title)) {
            // Check if the string contains Chinese characters
            if (preg_match("/\p{Han}/u", $request->title)) {
                // If the string contains Chinese characters, keep them in the slug
                $slug = preg_replace('/[^\p{Han}\s]+/u', '-', $request->title);
            } else {
                // If the string doesn't contain Chinese characters, transliterate it to ASCII and replace non-alphanumeric characters with a dash
                $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $slug);
                $slug = preg_replace('/[^\w\s]+/', '-', $slug);
            }

            // Replace spaces and multiple dashes with a single dash
            $slug = preg_replace('/\s+/', '-', $slug);
            $slug = preg_replace('/-+/', '-', $slug);
            // Convert the string to lowercase
            $slug = strtolower($slug);
        } else {
            // use normal way
            $slug = Str::slug($request->title);
        }

        $article = Article::create([
            'title' => $request->title,
            'type' => $request->type,
            'slug' => $slug,
            'excerpt' => ($request->excerpt) ?? null,
            'body' => $request->body,
            'status' => $request->status, // default is draft
            'published_at' => ($request->published_at) ? Carbon::parse($request->published_at)->toDateTimeString() : null,
            'user_id' => $user->id,
        ]);

        // if request->images attach images from user_uploads to article_images collection media library
        if ($request->has('images')) {
            $userUploads = $user->getMedia(User::USER_UPLOADS)->whereIn('id', $request->images);
            $userUploads->each(function ($media) use ($article) {
                // move to article_gallery collection of the created article
                Log::info('Media moved', ['media' => $media]);
                $media->move($article, Article::MEDIA_COLLECTION_NAME);
            });
        }

        // if request->video attach video from user_videos to article_videos collection media library
        if ($request->has('video')) {
            $userVideos = $user->getMedia(User::USER_VIDEO_UPLOADS)->whereIn('id', $request->video);
            $userVideos->each(function ($media) use ($article) {
                // move to article_videos collection of the created article
                Log::info('Media moved', ['media' => $media]);
                $media->move($article, Article::MEDIA_COLLECTION_NAME);
            });
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
                return ArticleTag::firstOrCreate(['name' => $tag, 'user_id' => auth()->id()])->id;
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
    public function show($id) {
        $article = Article::with('user', 'comments', 'interactions', 'media', 'categories', 'tags')
            ->published()
            ->whereDoesntHave('hiddenUsers', function ($query) {
                $query->where('user_id', auth()->user()->id);
            })
            ->findOrFail($id);

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
     * @bodyParam video file The video ID of the article.
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
        $article = Article::where('id', $id)->where('user_id', auth()->user()->id)
            ->first();

        if ($article) {

            $article->update([
                'body' => $request->body,
                'status' => $request->status,
            ]);

            // if request->images attach images from user_uploads to article_images collection media library
            if ($request->has('images')) {
                $userUploads = $user->getMedia(User::USER_UPLOADS)->whereIn('id', $request->images);
                $userUploads->each(function ($media) use ($article) {
                    // move to article_gallery collection of the created article
                    Log::info('Media moved', ['media' => $media]);
                    $media->move($article, Article::MEDIA_COLLECTION_NAME);
                });
            }

            // detach and delete images no longer in request->images
            if ($request->has('images') || $request->has('video')) {
                $article->getMedia(Article::MEDIA_COLLECTION_NAME)->each(function ($media) use ($request) {
                    // images gallery
                    if (!in_array($media->id, $request->images)) {
                        $media->delete();
                    }
                    // video
                    if (!in_array($media->id, $request->video)) {
                        $media->delete();
                    }
                });
            }

            // sync category
            if ($request->has('categories')) {
                // explode categories
                $category_ids = explode(',', $request->categories);
                // check if category exists before sync
                $categories = ArticleCategory::whereIn('id', $category_ids)->pluck('id');
                $article->categories()->sync($categories);
            }

            // sync tags
            if ($request->has('tags')) {
                $tags = explode(',', $request->tags);
                $tags = array_map(function ($tag) {
                    return Str::startsWith($tag, '#') ? $tag : '#' . $tag;
                }, $tags);

                // create or update tags
                $tags = collect($tags)->map(function ($tag) {
                    return ArticleTag::firstOrCreate(['name' => $tag, 'user_id' => auth()->id()])->id;
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
     * @param ArticleImagesUploadRequest $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Article
     * @bodyParam images file required The images to upload.
     * @bodyParam is_cover boolean used to set this image as a cover. Example:1
     * @response scenario=success {
     * "uploaded": [
     *     {
     *        "id": 1,
     *        "name": "image.jpg",
     *        "url": "http://localhost:8000/storage/user_uploads/1/image.jpg",
     *        "size": 12345,
     *        "type": "image/jpeg"
     *    }
     * ]
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["images": ["The images field is required."] ]}
     */
    public function postGalleryUpload(ArticleImagesUploadRequest $request)
    {
        $user = auth()->user();
        $images = [];
        // if request images is not array wrap it in array
        if (!is_array($request->images)) {
            // upload via spatie medialibrary
            // single image
            $uploaded = $user->addMedia($request->images)
                ->withCustomProperties(['is_cover' => $request->is_cover])
                ->toMediaCollection(
                    'user_uploads',
                    (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')),
                );
            return response()->json([
                'uploaded' => [
                    [
                        'id' => $uploaded->id,
                        'name' => $uploaded->file_name,
                        'url' => $uploaded->getUrl(),
                        'size' => $uploaded->size,
                        'type' => $uploaded->mime_type,
                    ],
                ],
            ]);
        } else {
            // multiple images
            $uploaded = collect($request->images)->map(function ($image) use ($user) {
                return $user->addMedia($image)
                    ->withCustomProperties(['is_cover' => $request->is_cover])
                    ->toMediaCollection(
                        'user_uploads',
                        (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')),
                );
            });
            $uploaded->each(function ($image) use (&$images) {
                $images[] = [
                    'id' => $image->id,
                    'name' => $image->file_name,
                    'url' => $image->getUrl(),
                    'size' => $image->size,
                    'type' => $image->mime_type,
                ];
            });
            return response()->json([
                'uploaded' => $images,
            ]);
        }
    }

    /**
     * Upload Video for Article
     *
     * Video size must not larger than 500MB, will stream video response back to client on progress via header X-Upload-Progress / calculate your own using X-Content-Duration
     *
     * Must be able to stream completion percentage back to client
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Article
     * @bodyParam video file required The video to upload.
     * @response scenario=success {
     * "url" : "http://localhost:8000/storage/user_video_uploads/1/video.mp4"
     * }
     */
    public function postVideoUpload(Request $request) {
        // validate video size must not larger than 500MB
        $request->validate([
            'video' => 'required|file|max:'.config('app.max_size_per_video_kb'),
        ]);X
        $videoFile = $request->file('video');
        $user = auth()->user();

        // Create new media item in the "user_uploads" collection
        $media = $user->addMedia($videoFile)
        ->toMediaCollection(User::USER_VIDEO_UPLOADS,
                (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default'))
        );

        $filesystem = config('filesystems.default');

        if ($filesystem == 's3' || $filesystem == 's3_public') {
            $fullUrl = Storage::disk(config('filesystems.default'))->url($media->getPath());
            $stream = Storage::disk(config('filesystems.default'))->readStream($media->getPath());
            $filesize = Storage::disk(config('filesystems.default'))->size($media->getPath());
        } else {
            $fullUrl = Storage::url($media->getPath());
            $stream = Storage::readStream($media->getPath());
            $filesize = Storage::size($media->getPath());
        }

        $chunksize = 1024 * 1024; // 1MB chunks
        $bytesRead = 0;

        while (!feof($stream)) {
            $chunk = fread($stream, $chunksize);
            $bytesRead += strlen($chunk);
            $progress = min(100, round($bytesRead / $filesize * 100));

            $response = response($chunk, 200, [
                'Content-Type' => $videoFile->getClientMimeType(),
                'X-Upload-Progress' => $progress,
                'X-Content-Duration' => $filesize,
                'X-Content-Url' => $fullUrl,
                'Media-Id' => $media->id,
            ]);

            ob_end_clean();
            $response->send();
            flush();
        }

        fclose($stream);
        return response()->json(['url' => $fullUrl]);
    }
    
    /**
     * Report an article
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Article
     * @subgroup Reports
     * @bodyParam article integer required The id of the article. Example: 1
     * @bodyParam reason string required The reason for reporting the comment. Example: Spam
     * @bodyParam violation_type required The violation type of this report
     * @bodyParam violation_level required The violation level of this report
     * @response scenario=success {
     * "message": "Comment reported",
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["article_id": ["The Article Id field is required."] ]}
     * @response status=422 scenario="Invalid Form Fields" {"message": "You have already reported this comment" ]}
     */
    public function postReportArticle(Request $request)
    {
        // validate
        $request->validate([
           'article_id' => 'required|integer',
           'reason' => 'required|string',
           'violation_type' => 'required|string',
           'violation_level' => 'required|integer',
        ]);
        // find article
        $article = Article::where('id', request('article_id'))->firstOrFail();
        // check if user has reported this comment before if not create
        if (!$article->reports()->where('user_id', auth()->id())->exists()) {
            $article->reports()->create([
                'user_id' => auth()->id(),
                'reason' => request('reason'),
                'violation_type' => request('violation_type'),
                'violation_level' => request('violation_level'),
            ]);
        } else {
            return response()->json(['message' => 'You have already reported this comment'], 422);
        }
        return response()->json(['message' => 'Comment reported']);
    }
}
