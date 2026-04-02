<?php

namespace App\Services\Impacts;

use App\Models\AdminUser;
use App\Models\Impact;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImpactService
{
    public function submitImpact(User $user, array $data): Impact
    {
        $impact = Impact::create([
            'user_id' => $user->id,
            'impacted_peer_id' => $data['impacted_peer_id'],
            'impact_date' => $data['date'] ?? now()->toDateString(),
            'action' => $data['action'],
            'story_to_share' => $data['story_to_share'],
            'life_impacted' => max(1, (int) ($data['life_impacted'] ?? 1)),
            'additional_remarks' => $data['additional_remarks'] ?? null,
            'requires_leadership_approval' => (bool) config('impact.requires_leadership_approval', true),
            'status' => 'pending',
        ]);

        Log::info('impact.submitted', [
            'impact_id' => (string) $impact->id,
            'user_id' => (string) $user->id,
            'impacted_peer_id' => (string) $impact->impacted_peer_id,
        ]);

        $this->notify((string) $user->id, 'impact_submitted', [
            'impact_id' => (string) $impact->id,
            'title' => 'Impact submitted',
            'body' => 'Your impact has been submitted and is pending review.',
        ]);

        return $impact;
    }

    public function approveImpact(Impact|string $impactOrId, User|AdminUser|string $adminOrId, ?string $reviewRemarks = null): Impact
    {
        return DB::transaction(function () use ($impactOrId, $adminOrId, $reviewRemarks) {
            $impactId = $impactOrId instanceof Impact ? (string) $impactOrId->getKey() : (string) $impactOrId;
            $adminId = $this->resolveAdminId($adminOrId);

            $impact = Impact::query()->with('user')->lockForUpdate()->findOrFail($impactId);

            if ($impact->status === 'approved') {
                return $impact;
            }

            if ($impact->status === 'rejected') {
                throw new \RuntimeException('Rejected impact cannot be approved.');
            }

            $impact->status = 'approved';
            $impact->approved_by = $adminId;
            $impact->approved_at = now();
            $impact->timeline_posted_at = now();
            $impact->rejected_by = null;
            $impact->rejected_at = null;
            $impact->review_remarks = $reviewRemarks;
            $impact->save();

            User::query()
                ->where('id', $impact->user_id)
                ->update([
                    'life_impacted_count' => DB::raw('COALESCE(life_impacted_count, 0) + ' . max(1, (int) ($impact->life_impacted ?? 1))),
                ]);

            Log::info('impact.approved', [
                'impact_id' => (string) $impact->id,
                'user_id' => (string) $impact->user_id,
                'approved_by' => $adminId,
            ]);

            Log::info('impact.life_impacted_incremented', [
                'impact_id' => (string) $impact->id,
                'user_id' => (string) $impact->user_id,
                'incremented_by' => max(1, (int) ($impact->life_impacted ?? 1)),
            ]);

            $this->notify((string) $impact->user_id, 'impact_approved', [
                'impact_id' => (string) $impact->id,
                'title' => 'Impact approved',
                'body' => 'Your impact has been approved and published.',
            ]);

            return $impact->fresh(['user', 'impactedPeer']);
        });
    }

    public function rejectImpact(Impact|string $impactOrId, User|AdminUser|string $adminOrId, ?string $reviewRemarks = null): Impact
    {
        return DB::transaction(function () use ($impactOrId, $adminOrId, $reviewRemarks) {
            $impactId = $impactOrId instanceof Impact ? (string) $impactOrId->getKey() : (string) $impactOrId;
            $adminId = $this->resolveAdminId($adminOrId);

            $impact = Impact::query()->lockForUpdate()->findOrFail($impactId);

            if ($impact->status === 'rejected') {
                return $impact;
            }

            if ($impact->status === 'approved') {
                throw new \RuntimeException('Approved impact cannot be rejected.');
            }

            $impact->status = 'rejected';
            $impact->rejected_by = $adminId;
            $impact->rejected_at = now();
            $impact->approved_by = null;
            $impact->approved_at = null;
            $impact->timeline_posted_at = null;
            $impact->review_remarks = $reviewRemarks;
            $impact->save();

            Log::info('impact.rejected', [
                'impact_id' => (string) $impact->id,
                'user_id' => (string) $impact->user_id,
                'rejected_by' => $adminId,
            ]);

            $this->notify((string) $impact->user_id, 'impact_rejected', [
                'impact_id' => (string) $impact->id,
                'title' => 'Impact rejected',
                'body' => 'Your impact was reviewed and rejected.',
                'review_remarks' => $reviewRemarks,
            ]);

            return $impact;
        });
    }

    private function resolveAdminId(User|AdminUser|string $adminOrId): string
    {
        if ($adminOrId instanceof User || $adminOrId instanceof AdminUser) {
            return (string) $adminOrId->getKey();
        }

        return (string) $adminOrId;
    }

    private function notify(string $userId, string $type, array $payload): void
    {
        Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'payload' => $payload,
            'is_read' => false,
            'created_at' => now(),
            'read_at' => null,
        ]);
    }
}
