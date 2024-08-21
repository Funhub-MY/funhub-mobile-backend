<?php

namespace App\Http\Resources;

use App\Models\Article;
use App\Models\Comment;
use App\Models\Interaction;
use App\Models\SystemNotification;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
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
                    if ($media) {
                        $article_cover = $media->getFullUrl();
                    }
                }
            } else if ($this->data['object'] == Interaction::class) {
                $article = $object->interactable;
                if ($article) {
                    $article_id = $article->id;
                    $article_type = $article->type;
                    $media = $article->getMedia(Article::MEDIA_COLLECTION_NAME)->first();
                    if ($media) {
                        $article_cover = $media->getFullUrl();
                    }
                }
            } else if ($this->data['object'] == Article::class) {
                $article = Article::find($this->data['object_id']);
                if ($article) {
                    $article_id = $this->data['object_id'];
                    $article_type = $article->type;
                    $media = $article->getMedia(Article::MEDIA_COLLECTION_NAME)->first();
                    if ($media) {
                        $article_cover = $media->getFullUrl();
                    }
                }
            } else if ($this->data['object'] == \App\Models\Mission::class) {
                // get if user claimed this mission before or not
                $missionUser = DB::table('missions_users')->where('mission_id', $this->data['object_id'])
                    ->where('user_id', auth()->id())
                    ->orderBy('id', 'desc')
                    ->first();
                $mission_claimed = false;
                $mission_completed = false;
                if ($missionUser) {
                    if ($missionUser->is_completed) {
                        $mission_completed = true;
                    }

                    if ($missionUser->claimed_at || $missionUser->last_rewarded_at) {
                        $mission_claimed = true;
                    } else {
                        $mission_claimed = false;
                    }
                }

                // appends to extra
                $this->data['extra'] = [
                    'mission_id' => $this->data['object_id'],
                    'mission_claimed' => $mission_claimed,
                    'mission_completed' => $mission_completed,
                ];
            }
        }

        $array = [
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
            'link_to' => $this->data['link_to'] ?? null,
            'action' => $this->data['action'] ?? null,
            'from_user' => new UserResource($this->from_user) ?? null,
            'is_read' => $this->read_at ? true : false,
            'extra' => $this->extra ?? null,
            'created_at_raw' => $this->created_at,
            'created_at' => $this->created_at->diffForHumans(),
        ];

        if ($this->data['object'] == SystemNotification::class) {
            $array['redirect'] = $this->data['redirect'] ?? null;
        }

        return $array;
    }
}
