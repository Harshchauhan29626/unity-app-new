<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\BlockPeerRequest;
use App\Http\Resources\BlockedPeerResource;
use App\Models\PeerBlock;
use App\Models\User;
use App\Services\Blocks\PeerBlockService;
use Illuminate\Http\Request;

class PeerBlockController extends BaseApiController
{
    public function __construct(private readonly PeerBlockService $peerBlockService)
    {
    }

    public function index(Request $request)
    {
        $authUser = $request->user();

        $blocks = PeerBlock::query()
            ->with('blockedUser:id,display_name,first_name,last_name,company_name,designation,profile_photo_url,profile_photo_file_id')
            ->where('blocker_user_id', (string) $authUser->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'count' => $this->peerBlockService->blockedCountFor((string) $authUser->id),
            'items' => BlockedPeerResource::collection($blocks),
        ]);
    }

    public function store(BlockPeerRequest $request, string $user)
    {
        $authUser = $request->user();
        $target = User::query()->find($user);

        if (! $target) {
            return $this->error('Peer not found.', 404);
        }

        if ((string) $authUser->id === (string) $target->id) {
            return $this->error('You cannot block yourself.', 422);
        }

        $alreadyBlocked = $this->peerBlockService->hasBlocked((string) $authUser->id, (string) $target->id);

        $this->peerBlockService->block($authUser, $target, $request->input('reason'));

        return $this->success(null, $alreadyBlocked ? 'Peer is already blocked.' : 'Peer blocked successfully.');
    }

    public function destroy(Request $request, string $user)
    {
        $authUser = $request->user();
        $target = User::query()->find($user);

        if (! $target) {
            return $this->error('Peer not found.', 404);
        }

        if ((string) $authUser->id === (string) $target->id) {
            return $this->error('You cannot unblock yourself.', 422);
        }

        $unblocked = $this->peerBlockService->unblock($authUser, $target);

        return $this->success(null, $unblocked ? 'Peer unblocked successfully.' : 'Peer is already unblocked.');
    }

    public function status(Request $request, string $user)
    {
        $authUser = $request->user();
        $target = User::query()->find($user);

        if (! $target) {
            return $this->error('Peer not found.', 404);
        }

        if ((string) $authUser->id === (string) $target->id) {
            return $this->error('Peer not found.', 404);
        }

        $isBlockedByMe = $this->peerBlockService->hasBlocked((string) $authUser->id, (string) $target->id);
        $hasBlockedMe = $this->peerBlockService->hasBlocked((string) $target->id, (string) $authUser->id);

        return $this->success([
            'is_blocked_by_me' => $isBlockedByMe,
            'has_blocked_me' => $hasBlockedMe,
            'cannot_interact' => $isBlockedByMe || $hasBlockedMe,
        ]);
    }
}
