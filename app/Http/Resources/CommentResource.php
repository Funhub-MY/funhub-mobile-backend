<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;
use App\Models\User;

class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $commentBody = null;
        if ($this->user->status == User::STATUS_ARCHIVED) {
            $commentBody = '注销账号评论已被删除';
        } else {
            $commentBody = $this->body;
        }

        // get taggeed users
        $taggedUsers = [];
        if ($this->taggedUsers) {
            foreach ($this->taggedUsers as $taggedUser) {
                $name = null;
                $username = null;
                $avatar_url = null;
                $avatar_thumb_url = null;
                if ($taggedUser->status == User::STATUS_ARCHIVED) {
                    $name = '用户已注销';
                    $username = '用户已注销';
                    $avatar_url = null;
                    $avatar_thumb_url = null;
                } else {
                    $name = $taggedUser->name;
                    $username = $taggedUser->username;
                    $avatar_url = $taggedUser->avatar_url;
                    $avatar_thumb_url = $taggedUser->avatar_thumb_url;
                }

                $taggedUsers[] = [
                    'id' => $taggedUser->id,
                    'name' => $name,
                    'username' => $username,
                    'avatar_url' => $avatar_url,
                    'avatar_thumb' => $avatar_thumb_url,
                ];
            }
        }

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'reply_to_id' => $this->reply_to_id,
            'reply_to_comment_user' => ($this->replyTo) ? [
                'id' => $this->replyTo->user->id,
                'name' => $this->replyTo->user->name,
            ] : null,
            'commentable_id' => $this->commentable_id,
            'user' => new UserResource($this->user),
            'counts' => [
                'likes' => $this->likes()->count(),
                'replies' => $this->replies_count ?? 0,
            ],
            'tagged_users' => $taggedUsers,
            'body' => $commentBody,
            'liked_by_user' => $this->likes->contains('user_id', auth()->id()),
            'likes' => CommentLikeResource::collection($this->likes),
            'replies' => ($this->parent_id) ? null : CommentResource::collection($this->replies), // only applicable for top level comment
            'is_reply' => $this->parent_id ? true : false,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->created_at->diffForHumans(),
        ];
    }
}
