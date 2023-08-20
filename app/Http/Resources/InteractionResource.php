<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;
use App\Models\Interaction;
use App\Models\User;

class InteractionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $name = null;
        $avatar = null;
        if ($this->user->status == User::STATUS_ARCHIVED) {
            $name = '用户已注销';
            $avatar = null;
        } else {
            $name = $this->user->name;
            $avatar = $this->user->avatar_url;
        }

        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $name,
                'avatar' => $avatar,
            ],
            'type' => $this->type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'share_url' => ($this->type == Interaction::TYPE_SHARE) ? $this->share_url : null,
            'created_at_diff' => $this->created_at->diffForHumans(),
            'updated_at_diff' => $this->created_at->diffForHumans(),
        ];
    }
}
