<?php

namespace App\Http\Resources\LifeImpact;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class LifeImpactHistoryResource extends JsonResource
{
    public function toArray($request): array
    {
        $performedBy = $this->triggeredByUser ?: $this->user;
        $activityDetails = is_array($this->meta) ? $this->meta : [];
        $affectedUserId = (string) ($activityDetails['to_user_id'] ?? $activityDetails['affected_user_id'] ?? '');
        $affectedUser = $affectedUserId !== ''
            ? User::query()
                ->select(['id', 'first_name', 'last_name', 'display_name', 'email'])
                ->find($affectedUserId)
            : null;

        return [
            'id' => (string) $this->id,
            'activity_type' => (string) $this->activity_type,
            'impact_value' => $this->resolveImpactValue(),
            'title' => (string) $this->title,
            'description' => $this->description,
            'performed_by' => $performedBy ? [
                'id' => (string) $performedBy->id,
                'first_name' => $performedBy->first_name,
                'last_name' => $performedBy->last_name,
                'email' => $performedBy->email,
                'life_impacted_count' => (int) ($performedBy->life_impacted_count ?? 0),
            ] : null,
            'affected_user' => $affectedUser ? [
                'id' => (string) $affectedUser->id,
                'first_name' => $affectedUser->first_name,
                'last_name' => $affectedUser->last_name,
                'email' => $affectedUser->email,
            ] : null,
            'activity_details' => $activityDetails,
            'activity_id' => $this->activity_id ? (string) $this->activity_id : null,
            'triggered_by_user' => $this->whenLoaded('triggeredByUser', function () {
                return [
                    'id' => (string) $this->triggeredByUser->id,
                    'first_name' => $this->triggeredByUser->first_name,
                    'last_name' => $this->triggeredByUser->last_name,
                    'display_name' => $this->triggeredByUser->display_name,
                ];
            }),
            'created_at' => $this->created_at,
        ];
    }
}
