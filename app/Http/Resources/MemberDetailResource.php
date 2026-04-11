<?php

namespace App\Http\Resources;

use App\Models\JoinedCircleCategory;
use Illuminate\Support\Facades\Schema;

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
        $data['categories'] = $this->resolveJoinedCircleCategories();

        return $data;
    }

    private function resolveJoinedCircleCategories(): array
    {
        if (! Schema::hasTable('joined_circle_categories')) {
            return [];
        }

        return JoinedCircleCategory::query()
            ->where('user_id', $this->id)
            ->with([
                'circle:id,name',
                'level1Category:id,name',
                'level2Category:id,name',
                'level3Category:id,name',
                'level4Category:id,name',
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (JoinedCircleCategory $row): array {
                return [
                    'circle_id' => $row->circle_id,
                    'circle_name' => $row->circle?->name,
                    'level1_category' => $row->level1Category
                        ? ['id' => $row->level1Category->id, 'name' => $row->level1Category->name]
                        : null,
                    'level2_category' => $row->level2Category
                        ? ['id' => $row->level2Category->id, 'name' => $row->level2Category->name]
                        : null,
                    'level3_category' => $row->level3Category
                        ? ['id' => $row->level3Category->id, 'name' => $row->level3Category->name]
                        : null,
                    'level4_category' => $row->level4Category
                        ? ['id' => $row->level4Category->id, 'name' => $row->level4Category->name]
                        : null,
                ];
            })
            ->values()
            ->all();
    }
}
