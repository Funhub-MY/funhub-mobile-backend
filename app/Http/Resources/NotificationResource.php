<?php

namespace App\Http\Resources;

use App\Models\Article;
use App\Models\Comment;
use App\Models\Interaction;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // get article id if object is comment / interaction
        $article_id = null;
        $article_type = null;
        $article_cover = null;

        $object = $this->data['object']::find($this->data['object_id']);

        if ($object) {
            if ($this->data['object'] == Comment::class) {
                $article =  $object->commentable;
                if ($article) {
                    $article_id = $article->id;
                    $article_type = $article->type;
                    $media = $article->getMedia(Article::MEDIA_COLLECTION_NAME)->first();
                    if ($media)
                        $article_cover = $media->getFullUrl();
                    }
                }
            } else if ($this->data['object'] == Interaction::class) {
                $article = $object->interactable;
                if ($article) {
                    $article_id = $article->id;
                    $article_type = $article->type;
                    $media = $article->getMedia(Article::MEDIA_COLLECTION_NAME)->first();
                    if ($media)
                        $article_cover = $media->getFullUrl();
                    }
                }
            }
        }

        return [
            'id' => $this->id,
            'title' => $this->data['title'] ?? null,
            'message' => $this->data['message'] ?? null,
            'object' => $this->data['object'] ?? null,
            'object_id' => $this->data['object_id'] ?? null,
            'article_id' => $article_id,
            'article_type' => $article_type,
            'article_cover' => $article_cover,
            'link_to_url' => $this->data['link_to_url'] ?? null,
            'link_to_object' => $this->data['link_to_object'] ?? null,
            'action' => $this->data['action'] ?? null,
            'from_user' => new UserResource($this->from_user) ?? null,
            'is_read' => $this->read_at ? true : false,
            'created_at_raw' => $this->created_at,
            'created_at' => $this->created_at->diffForHumans(),
        ];
    }
}
