<?php

namespace App\Http\Resources;

use App\Models\CampaignQuestion;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignQuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $answers = [];
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
        ];
    }
}
