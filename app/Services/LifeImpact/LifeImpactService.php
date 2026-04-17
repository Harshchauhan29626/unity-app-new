<?php

namespace App\Services\LifeImpact;

use App\Models\Impact;
use App\Models\LifeImpactHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LifeImpactService
{
    public function addLifeImpact(
        string $userId,
        ?string $triggeredByUserId,
        string $activityType,
        ?string $activityId = null,
        int $impactValue = 0,
        string $title = '',
        ?string $description = null,
        array $meta = [],
    ): int {
        $impactValue = (int) $impactValue;
        $activityId = (is_string($activityId) && Str::isUuid($activityId))
            ? $activityId
            : null;

        if ($impactValue <= 0) {
            return $this->getCurrentTotal($userId);
        }

        return (int) DB::transaction(function () use ($userId, $impactValue, $activityType, $title, $triggeredByUserId, $activityId, $description, $meta) {
            DB::table('users')
                ->where('id', $userId)
                ->update([
                    'life_impacted_count' => DB::raw('COALESCE(life_impacted_count, 0) + ' . $impactValue),
                    'updated_at' => now(),
                ]);

            LifeImpactHistory::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'triggered_by_user_id' => $triggeredByUserId,
                'activity_type' => $activityType,
                'activity_id' => $activityId,
                'impact_value' => $impactValue,
                'title' => $title,
                'description' => $description,
                'meta' => $meta ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->getCurrentTotal($userId);
        });
    }

    public function incrementAndLog(
        string $userId,
        int $points,
        string $activityType,
        string $title,
        ?string $triggeredByUserId = null,
        ?string $activityId = null,
        ?string $description = null,
        ?array $meta = null,
    ): int {
        return $this->addLifeImpact(
            $userId,
            $triggeredByUserId,
            $activityType,
            $activityId,
            (int) $points,
            $title,
            $description,
            $meta ?? []
        );
    }

    public function getCurrentTotal(string $userId): int
    {
        return (int) (DB::table('users')->where('id', $userId)->value('life_impacted_count') ?? 0);
    }

    public function recordApprovedImpactHistory(Impact $impact, ?string $approvedByAdminId = null): array
    {
        $impactId = (string) $impact->id;
        $userId = (string) $impact->user_id;
        $triggeredByUserId = (string) $impact->user_id;
        $impactValue = max(1, (int) ($impact->life_impacted ?? 1));
        $actionLabel = trim((string) ($impact->action ?? 'Impact Approved'));
        $actionKey = Str::of($actionLabel)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
        $remarks = $impact->additional_remarks ?: $impact->review_remarks;

        return DB::transaction(function () use (
            $impact,
            $impactId,
            $userId,
            $triggeredByUserId,
            $impactValue,
            $actionLabel,
            $actionKey,
            $remarks,
            $approvedByAdminId
        ): array {
            Log::info('impact.approval.started', [
                'impact_id' => $impactId,
                'user_id' => $userId,
                'triggered_by_user_id' => $triggeredByUserId,
                'action' => $actionKey,
            ]);

            $existing = LifeImpactHistory::query()
                ->where('activity_type', 'impact')
                ->where('activity_id', $impactId)
                ->where('user_id', $userId)
                ->first();

            if ($existing) {
                Log::info('impact.approval.history_exists', [
                    'impact_id' => $impactId,
                    'user_id' => $userId,
                    'history_id' => (string) $existing->id,
                ]);

                $total = $this->recomputeTotalFromHistory($userId);

                Log::info('impact.approval.total_recomputed', [
                    'impact_id' => $impactId,
                    'user_id' => $userId,
                    'total_life_impacted' => $total,
                ]);

                return [
                    'created' => false,
                    'history_id' => (string) $existing->id,
                    'total_life_impacted' => $total,
                ];
            }

            $meta = array_filter([
                'impact_id' => $impactId,
                'impact_date' => optional($impact->impact_date)?->toDateString(),
                'action' => (string) $impact->action,
                'action_key' => $actionKey,
                'action_label' => $actionLabel,
                'impact_value' => $impactValue,
                'impacted_peer_id' => $impact->impacted_peer_id ? (string) $impact->impacted_peer_id : null,
                'affected_user_id' => $impact->impacted_peer_id ? (string) $impact->impacted_peer_id : null,
                'story_to_share' => $impact->story_to_share,
                'additional_remarks' => $impact->additional_remarks,
                'review_remarks' => $impact->review_remarks,
                'approved_by' => $approvedByAdminId,
                'approved_at' => optional($impact->approved_at)->toISOString(),
            ], fn ($value) => $value !== null && $value !== '');

            $payload = [
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'triggered_by_user_id' => $triggeredByUserId !== '' ? $triggeredByUserId : null,
                'activity_type' => 'impact',
                'activity_id' => $impactId,
                'impact_value' => $impactValue,
                'title' => $actionLabel !== '' ? $actionLabel : 'Impact Approved',
                'description' => 'Impact approved: '.($actionLabel !== '' ? $actionLabel : 'Impact action'),
                'meta' => $meta,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('life_impact_histories', 'life_impacted')) {
                $payload['life_impacted'] = $impactValue;
            }

            if (Schema::hasColumn('life_impact_histories', 'counted_in_total')) {
                $payload['counted_in_total'] = true;
            }

            if (Schema::hasColumn('life_impact_histories', 'impact_category')) {
                $payload['impact_category'] = null;
            }

            if (Schema::hasColumn('life_impact_histories', 'action_key')) {
                $payload['action_key'] = $actionKey !== '' ? $actionKey : null;
            }

            if (Schema::hasColumn('life_impact_histories', 'action_label')) {
                $payload['action_label'] = $actionLabel !== '' ? $actionLabel : null;
            }

            if (Schema::hasColumn('life_impact_histories', 'remarks')) {
                $payload['remarks'] = $remarks;
            }

            DB::table('life_impact_histories')->insert($payload);

            $historyId = (string) $payload['id'];
            $total = $this->recomputeTotalFromHistory($userId);

            Log::info('impact.approval.history_created', [
                'impact_id' => $impactId,
                'user_id' => $userId,
                'history_id' => $historyId,
                'impact_value' => $impactValue,
            ]);

            Log::info('impact.approval.total_recomputed', [
                'impact_id' => $impactId,
                'user_id' => $userId,
                'total_life_impacted' => $total,
            ]);

            return [
                'created' => true,
                'history_id' => $historyId,
                'total_life_impacted' => $total,
            ];
        });
    }

    public function recomputeTotalFromHistory(string $userId): int
    {
        $query = DB::table('life_impact_histories')->where('user_id', $userId);

        if (Schema::hasColumn('life_impact_histories', 'counted_in_total')) {
            $query->where(function ($subQuery): void {
                $subQuery->where('counted_in_total', true)
                    ->orWhereNull('counted_in_total');
            });
        }

        $sumExpression = Schema::hasColumn('life_impact_histories', 'life_impacted')
            ? 'COALESCE(life_impacted, impact_value, 0)'
            : 'COALESCE(impact_value, 0)';

        $sum = (int) $query->sum(DB::raw($sumExpression));

        DB::table('users')
            ->where('id', $userId)
            ->update([
                'life_impacted_count' => $sum,
                'updated_at' => now(),
            ]);

        return $sum;
    }
}
