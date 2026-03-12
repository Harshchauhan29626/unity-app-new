<?php

namespace App\Http\Resources;

class MemberDetailResource extends UserResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        $data['medal_rank'] = $this->coin_medal_rank;
        $data['title'] = $this->coin_milestone_title;
        $data['meaning_and_vibe'] = $this->coin_milestone_meaning;
        $data['contribution_award_name'] = $this->contribution_award_name;
        $data['contribution_recognition'] = $this->contribution_award_recognition;

        return $data;
    }
}
