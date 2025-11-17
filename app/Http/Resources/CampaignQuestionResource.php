<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use App\Models\CampaignQuestion;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignQuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $answers = $this->answer;
        if ($this->answer_type !== 'text') {
            $answers = json_decode($this->answer);
        }

        return [
              'campaign_id' => $this->campaign_id,
              'id' => $this->id,
              'brand' => $this->brand,
              'type' => $this->answer_type,
              'question' => $this->question,
              'question_banner' => $this->getFirstMediaUrl(CampaignQuestion::QUESTION_BANNER),
              'footer_banner' => $this->getFirstMediaUrl(CampaignQuestion::FOOTER_BANNER),
              'answer' => $answers,
              'default_answer' => $this->default_answer,
              'has_user_completed' => $this->hasUserCompleted,
        ];
    }
}
