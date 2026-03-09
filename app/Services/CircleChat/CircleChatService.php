<?php

namespace App\Services\CircleChat;

use App\Events\CircleChatMessageDeletedForAll;
use App\Events\CircleChatMessageSent;
use App\Events\CircleChatMessagesRead;
use App\Events\UserNotificationCreated;
use App\Http\Resources\CircleChatMessageResource;
use App\Models\Circle;
use App\Models\CircleChatMessage;
use App\Models\CircleChatMessageDeletion;
use App\Models\CircleChatMessageRead;
use App\Models\CircleMember;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CircleChatService
{
    public function __construct(
        private readonly CircleChatAccessService $accessService,
    ) {
    }

    public function getMessages(User $user, Circle $circle, int $perPage = 20, ?string $beforeMessageId = null): LengthAwarePaginator
    {
        $this->accessService->ensureUserIsCircleMember($user, $circle->id);

        $query = CircleChatMessage::query()
            ->where('circle_id', $circle->id)
            ->where('is_deleted_for_all', false)
            ->whereDoesntHave('deletions', fn ($q) => $q->where('user_id', $user->id))
            ->when($beforeMessageId, function ($q) use ($beforeMessageId) {
                $before = CircleChatMessage::query()->find($beforeMessageId);
                if ($before) {
                    $q->where('created_at', '<', $before->created_at);
                }
            })
            ->with([
                'sender:id,display_name,first_name,last_name,company_name,profile_photo_url',
                'replyTo:id,circle_id,sender_id,message_type,message_text,file_path,file_name,file_mime,file_size,thumbnail_path,created_at,updated_at',
                'replyTo.sender:id,display_name,first_name,last_name,company_name,profile_photo_url',
            ])
            ->withCount('reads')
            ->withExists(['reads as is_read_by_me' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderByDesc('created_at');

        $paginator = $query->paginate(max(1, min($perPage, 100)));

        $collection = $paginator->getCollection()->reverse()->values();
        $paginator->setCollection($collection);

        return $paginator;
    }

    public function sendMessage(User $user, Circle $circle, array $validated, ?UploadedFile $attachment): CircleChatMessage
    {
        $this->accessService->ensureUserIsCircleMember($user, $circle->id);

        return DB::transaction(function () use ($user, $circle, $validated, $attachment): CircleChatMessage {
            $filePayload = [
                'file_path' => null,
                'file_name' => null,
                'file_mime' => null,
                'file_size' => null,
                'thumbnail_path' => null,
            ];

            if ($attachment) {
                $filePayload = $this->storeAttachment($attachment);
            }

            $message = CircleChatMessage::query()->create([
                'circle_id' => $circle->id,
                'sender_id' => $user->id,
                'message_type' => $validated['message_type'],
                'message_text' => $validated['message_text'] ?? null,
                'reply_to_message_id' => $validated['reply_to_message_id'] ?? null,
                ...$filePayload,
            ]);

            CircleChatMessageRead::query()->create([
                'message_id' => $message->id,
                'user_id' => $user->id,
                'read_at' => now(),
            ]);

            $message->load([
                'sender:id,display_name,first_name,last_name,company_name,profile_photo_url',
                'replyTo:id,circle_id,sender_id,message_type,message_text,file_path,file_name,file_mime,file_size,thumbnail_path,created_at,updated_at',
                'replyTo.sender:id,display_name,first_name,last_name,company_name,profile_photo_url',
            ])->loadCount('reads')->loadExists(['reads as is_read_by_me' => fn ($q) => $q->where('user_id', $user->id)]);

            $this->notifyCircleMembers($circle, $user, $message);

            broadcast(new CircleChatMessageSent($circle->id, (new CircleChatMessageResource($message))->resolve()))->toOthers();

            return $message;
        });
    }

    public function markMessagesRead(User $user, Circle $circle, array $messageIds): int
    {
        $this->accessService->ensureUserIsCircleMember($user, $circle->id);

        $validMessageIds = CircleChatMessage::query()
            ->where('circle_id', $circle->id)
            ->whereIn('id', $messageIds)
            ->where('is_deleted_for_all', false)
            ->pluck('id');

        $alreadyRead = CircleChatMessageRead::query()
            ->where('user_id', $user->id)
            ->whereIn('message_id', $validMessageIds)
            ->pluck('message_id')
            ->all();

        $missingIds = array_values(array_diff($validMessageIds->all(), $alreadyRead));

        if ($missingIds === []) {
            return 0;
        }

        $now = now();

        $rows = array_map(fn (string $messageId): array => [
            'id' => (string) Str::uuid(),
            'message_id' => $messageId,
            'user_id' => $user->id,
            'read_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $missingIds);

        DB::table('circle_chat_message_reads')->insert($rows);

        broadcast(new CircleChatMessagesRead($circle->id, $missingIds, [
            'id' => (string) $user->id,
            'name' => trim((string) ($user->display_name ?: $user->first_name . ' ' . $user->last_name)),
        ]))->toOthers();

        return count($missingIds);
    }

    public function getReadDetails(User $user, Circle $circle, CircleChatMessage $message): array
    {
        $this->accessService->ensureUserIsCircleMember($user, $circle->id);
        $this->ensureMessageBelongsToCircle($message, $circle);

        if ($message->is_deleted_for_all) {
            throw new HttpException(404, 'Message not found in this circle.');
        }

        $reads = $message->reads()
            ->with('user:id,display_name,first_name,last_name,company_name,profile_photo_url')
            ->orderBy('read_at')
            ->get();

        return [
            'message_id' => (string) $message->id,
            'read_count' => $reads->count(),
            'readers' => $reads->map(function (CircleChatMessageRead $read): array {
                return [
                    'id' => (string) $read->user_id,
                    'name' => trim((string) (($read->user->display_name ?? '') ?: (($read->user->first_name ?? '') . ' ' . ($read->user->last_name ?? '')))),
                    'company_name' => $read->user->company_name,
                    'profile_photo_url' => $read->user->profile_photo_url,
                    'read_at' => $read->read_at,
                ];
            })->values(),
        ];
    }

    public function deleteForMe(User $user, Circle $circle, CircleChatMessage $message): void
    {
        $this->accessService->ensureUserIsCircleMember($user, $circle->id);
        $this->ensureMessageBelongsToCircle($message, $circle);

        CircleChatMessageDeletion::query()->firstOrCreate([
            'message_id' => $message->id,
            'user_id' => $user->id,
        ], [
            'deleted_at' => now(),
        ]);
    }

    public function deleteForAll(User $user, Circle $circle, CircleChatMessage $message): void
    {
        $this->accessService->ensureUserIsCircleMember($user, $circle->id);
        $this->ensureMessageBelongsToCircle($message, $circle);

        if ((string) $message->sender_id !== (string) $user->id) {
            throw new HttpException(403, 'Only sender can delete this message for all.');
        }

        if (! $message->is_deleted_for_all) {
            $message->forceFill([
                'is_deleted_for_all' => true,
                'deleted_for_all_at' => now(),
            ])->save();

            broadcast(new CircleChatMessageDeletedForAll($circle->id, $message->id))->toOthers();
        }
    }

    public function ensureMessageBelongsToCircle(CircleChatMessage $message, Circle $circle): void
    {
        if ((string) $message->circle_id !== (string) $circle->id) {
            throw new HttpException(404, 'Message not found in this circle.');
        }
    }

    private function storeAttachment(UploadedFile $file): array
    {
        $disk = config('filesystems.default', 'public');
        $folder = 'uploads/circle-chat/' . now()->format('Y/m/d');
        $safeName = preg_replace('/[^A-Za-z0-9\.\-_]/', '_', $file->getClientOriginalName()) ?: 'attachment';
        $storedPath = $file->storeAs($folder, (string) Str::uuid() . '_' . $safeName, $disk);

        return [
            'file_path' => $storedPath,
            'file_name' => $file->getClientOriginalName(),
            'file_mime' => $file->getMimeType() ?: $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'thumbnail_path' => null,
        ];
    }

    private function notifyCircleMembers(Circle $circle, User $sender, CircleChatMessage $message): void
    {
        $memberUserIds = CircleMember::query()
            ->where('circle_id', $circle->id)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->where('user_id', '!=', $sender->id)
            ->pluck('user_id');

        if ($memberUserIds->isEmpty()) {
            return;
        }

        $senderName = trim((string) ($sender->display_name ?: $sender->first_name . ' ' . $sender->last_name));
        $preview = $message->message_type === 'text'
            ? Str::limit((string) $message->message_text, 100)
            : ($message->message_type === 'image' ? '📷 Image' : '🎬 Video');

        /** @var Collection<int,string> $memberUserIds */
        foreach ($memberUserIds as $recipientId) {
            $notification = Notification::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => $recipientId,
                'type' => 'new_message',
                'payload' => [
                    'title' => 'New message in ' . $circle->name,
                    'body' => $senderName . ': ' . $preview,
                    'type' => 'circle_chat_message',
                    'circle_id' => (string) $circle->id,
                    'circle_name' => $circle->name,
                    'message_id' => (string) $message->id,
                    'sender_id' => (string) $sender->id,
                    'sender_name' => $senderName,
                    'sender_company_name' => $sender->company_name,
                    'sender_profile_photo_url' => $sender->profile_photo_url,
                    'message_type' => $message->message_type,
                    'preview_text' => $preview,
                ],
                'is_read' => false,
                'created_at' => now(),
                'read_at' => null,
            ]);

            event(new UserNotificationCreated((string) $recipientId, [
                'id' => (string) $notification->id,
                'title' => $notification->payload['title'],
                'body' => $notification->payload['body'],
                'type' => 'circle_chat_message',
                'payload' => $notification->payload,
                'is_read' => false,
                'created_at' => $notification->created_at,
            ]));
        }
    }
}
