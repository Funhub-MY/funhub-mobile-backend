<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;
use App\Models\Interaction;
use App\Models\User;

class InteractionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        // get the raw user relationship first
        $user = $this->resource->user;
        $name = null;
        $avatar = null;
        
        if ($user instanceof User) {
            if ($user->status == User::STATUS_ARCHIVED) {
                $name = '用户已注销';
            } else {
                $name = $user->name;
                // use the avatar thumb url which has a fallback to ui-avatars
                if ($user) {
                    $avatar = $user->avatar_thumb_url;
                }
            }
        }

        return [
            'id' => $this->id,
            'user' => $user instanceof User ? [
                'id' => $user->id,
                'name' => $name,
                'avatar' => $avatar,
            ] : null,
            'type' => $this->type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'share_url' => null,
            'created_at_diff' => $this->created_at ? $this->created_at->diffForHumans() : null,
            'updated_at_diff' => $this->updated_at ? $this->updated_at->diffForHumans() : null,
        ];
    }
}
