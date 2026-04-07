<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Chat\StoreChatRequest;
use App\Http\Requests\Chat\StoreMessageRequest;
use App\Http\Resources\ChatResource;
use App\Http\Resources\MessageResource;
use App\Models\FileModel;
use App\Models\Notification;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use App\Events\Chat\ChatReadUpdated;
use App\Events\Chat\MessageSent;
use App\Events\Chat\ChatTyping;
use App\Jobs\SendPushNotificationJob;
use App\Services\Blocks\PeerBlockService;
use App\Support\Chat\AuthorizesChatAccess;
use App\Support\Media\Probe;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatController extends BaseApiController
{
    use AuthorizesChatAccess;

    public function __construct(
        private readonly Probe $probe
    ) {
    }

    public function index(Request $request)
    {
        return $this->listChats($request);
    }

    public function listChats(Request $request)
    {
        $authUser = $request->user();
        $me = $authUser->id;

        $query = Chat::with(['user1', 'user2', 'lastMessage'])
            ->where(function ($q) use ($me) {
                $q->where('user1_id', $me)
                    ->orWhere('user2_id', $me);
            });

        $query->withCount(['messages as unread_count' => function ($q) use ($authUser) {
            $q->where('sender_id', '!=', $authUser->id)
                ->where('is_read', false)
                ->whereNull('deleted_at');
        }]);

        $chats = $query
            ->orderByRaw('last_message_at IS NULL ASC')
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get();

        return $this->success(ChatResource::collection($chats));
    }

    public function storeChat(StoreChatRequest $request, PeerBlockService $peerBlockService)
    {
        $authUserId = (string) auth()->id();
        $data = $request->validated();
        $otherUserId = (string) ($data['user_id'] ?? $request->input('user_id'));

        if ($authUserId === $otherUserId) {
            return $this->error('You cannot start a chat with yourself', 422);
        }


        if ($peerBlockService->isBlockedEitherWay($authUserId, $otherUserId)) {
            return $this->error('You cannot interact with this peer.', 422);
        }

        $otherUser = User::find($otherUserId);
        if (! $otherUser) {
            return $this->error('User not found', 404);
        }

        [$userSmall, $userBig] = strcmp($authUserId, $otherUserId) <= 0
            ? [$authUserId, $otherUserId]
            : [$otherUserId, $authUserId];

        $chat = Chat::where('user1_id', $userSmall)
            ->where('user2_id', $userBig)
            ->first();

        if (! $chat) {
            try {
                $chat = Chat::create([
                    'user1_id' => $userSmall,
                    'user2_id' => $userBig,
                ]);
            } catch (QueryException $e) {
                $chat = Chat::where('user1_id', $userSmall)
                    ->where('user2_id', $userBig)
                    ->first();

                if (! $chat) {
                    throw $e;
                }
            }
        }

        $chat->load([
            'user1',
            'user2',
            'lastMessage',
        ]);

        $chat->loadCount(['messages as unread_count' => function ($q) use ($authUserId) {
            $q->where('sender_id', '!=', $authUserId)
                ->where('is_read', false)
                ->whereNull('deleted_at');
        }]);

        return $this->success(new ChatResource($chat), 'Chat ready');
    }

    public function showChat(Request $request, string $id)
    {
        $authUser = $request->user();

        $chat = Chat::with(['user1', 'user2', 'lastMessage'])
            ->where('id', $id)
            ->where(function ($q) use ($authUser) {
                $q->where('user1_id', $authUser->id)
                    ->orWhere('user2_id', $authUser->id);
            })
            ->first();

        if (! $chat) {
            return $this->error('Chat not found', 404);
        }

        $chat->loadCount(['messages as unread_count' => function ($q) use ($authUser) {
            $q->where('sender_id', '!=', $authUser->id)
                ->where('is_read', false)
                ->whereNull('deleted_at');
        }]);

        return $this->success(new ChatResource($chat));
    }

    public function listMessages(Request $request, string $id)
    {
        $authUser = $request->user();

        $chat = Chat::where('id', $id)
            ->where(function ($q) use ($authUser) {
                $q->where('user1_id', $authUser->id)
                    ->orWhere('user2_id', $authUser->id);
            })
            ->first();

        if (! $chat) {
            return $this->error('Chat not found', 404);
        }

        $perPage = (int) $request->input('per_page', 50);
        $perPage = max(1, min($perPage, 100));

        $paginator = Message::with('sender')
            ->where('chat_id', $chat->id)
            ->whereNull('deleted_at')
            ->when((string) $chat->user1_id === (string) $authUser->id, function ($q) {
                $q->whereNull('deleted_for_user1_at');
            }, function ($q) {
                $q->whereNull('deleted_for_user2_at');
            })
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        $data = [
            'items' => MessageResource::collection($paginator),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];

        return $this->success($data);
    }

    public function storeMessage(StoreMessageRequest $request, string $id, PeerBlockService $peerBlockService)
    {
        $authUser = $request->user();
        $data = $request->validated() ?? [];

        $chat = Chat::where('id', $id)
            ->where(function ($q) use ($authUser) {
                $q->where('user1_id', $authUser->id)
                    ->orWhere('user2_id', $authUser->id);
            })
            ->first();

        if (! $chat) {
            return $this->error('Chat not found', 404);
        }


        $otherUserId = (string) ((string) $chat->user1_id === (string) $authUser->id ? $chat->user2_id : $chat->user1_id);

        if ($peerBlockService->isBlockedEitherWay((string) $authUser->id, $otherUserId)) {
            return $this->error('You cannot interact with this peer.', 422);
        }

        $filesInput = $request->file('files', []);
        $files = is_array($filesInput) ? $filesInput : ($filesInput ? [$filesInput] : []);

        $attachments = is_array($data['attachments'] ?? null) ? $data['attachments'] : [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $attachments[] = $this->storeAttachment($file, (string) $authUser->id);
        }

        $content = $this->normalizedContent($data['content_text'] ?? $data['content'] ?? null);

        $message = $chat->messages()->create([
            'sender_id' => $authUser->id,
            'content' => $content,
            'attachments' => $attachments ?: null,
            'is_read' => false,
        ]);

        $message->refresh();
        $message->load('sender');
        $messageAttachments = is_array($message->attachments) ? $message->attachments : [];

        $chat->last_message_id = $message->id;
        $chat->last_message_at = $message->created_at;
        $chat->save();

        $senderPayload = $this->formatUserPayload($authUser);
        $recipients = collect([$chat->user1_id, $chat->user2_id])
            ->filter()
            ->unique()
            ->reject(fn ($userId) => (string) $userId === (string) $authUser->id)
            ->values();

        foreach ($recipients as $recipientId) {
            Notification::create([
                'user_id' => $recipientId,
                'type' => 'new_message',
                'payload' => [
                    'chat_id' => (string) $chat->id,
                    'message_id' => (string) $message->id,
                    'sender' => $senderPayload,
                    'preview' => $this->messagePreview($message->content, $message->attachments),
                    'type' => 'chat_message',
                ],
                'is_read' => false,
                'created_at' => now(),
                'read_at' => null,
            ]);

            $receiverUser = User::find($recipientId);

            if (! $receiverUser) {
                continue;
            }

            Log::info('Dispatching chat push notification job', [
                'chat_id' => (string) $chat->id,
                'message_id' => (string) $message->id,
                'sender_id' => (string) $authUser->id,
                'receiver_id' => (string) $receiverUser->id,
            ]);

            $attachmentList = $messageAttachments;
            $firstImageAttachment = collect($attachmentList)
                ->first(fn ($attachment) => Arr::get($attachment, 'kind') === 'image');

            $imageUrl = null;
            $rawImageUrl = Arr::get($firstImageAttachment, 'url');

            if (is_string($rawImageUrl) && $rawImageUrl !== '') {
                $imageUrl = filter_var($rawImageUrl, FILTER_VALIDATE_URL)
                    ? $rawImageUrl
                    : $this->toAbsoluteFileUrl($rawImageUrl);
            }

            $hasImage = is_string($imageUrl) && $imageUrl !== '';

            $pushData = [
                'type' => 'chat',
                'chat_id' => (string) $chat->id,
                'sender_id' => (string) $authUser->id,
                'message_id' => (string) $message->id,
                'has_media' => $hasImage ? '1' : '0',
                'media_kind' => $hasImage ? 'image' : null,
            ];

            if ($hasImage) {
                $pushData['image_url'] = $imageUrl;
            }

            // Queue worker required to process push jobs: php artisan queue:work
            SendPushNotificationJob::dispatch(
                $receiverUser,
                $this->resolveDisplayName($authUser) ?: 'New message',
                $this->pushBody($message->content, $message->attachments),
                $pushData
            );
        }

        broadcast(new MessageSent($chat, $message, $authUser))->toOthers();

        return $this->success(new MessageResource($message), 'Message sent', 201);
    }


    private function normalizedContent(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || in_array(strtolower($trimmed), ['none', 'null'], true)) {
            return null;
        }

        return $trimmed;
    }

    private function messagePreview(mixed $content, mixed $attachments): string
    {
        $normalizedContent = $this->normalizedContent($content);
        if ($normalizedContent !== null) {
            return Str::limit($normalizedContent, 120, '');
        }

        $attachmentList = is_array($attachments) ? $attachments : [];

        return count($attachmentList) > 0 ? '📎 Attachment' : '';
    }

    private function pushBody(mixed $content, mixed $attachments): string
    {
        $normalizedContent = $this->normalizedContent($content);
        if ($normalizedContent !== null) {
            return Str::limit($normalizedContent, 120);
        }

        $attachmentList = is_array($attachments) ? $attachments : [];
        $hasImage = collect($attachmentList)
            ->contains(fn ($attachment) => Arr::get($attachment, 'kind') === 'image');

        if ($hasImage) {
            return '📷 Photo';
        }

        return count($attachmentList) > 0 ? '📎 Attachment' : '';
    }

    private function toAbsoluteFileUrl(string $relativeUrl): string
    {
        $baseUrl = rtrim((string) config('app.url', ''), '/');

        if ($baseUrl === '' && app()->bound('request')) {
            $baseUrl = rtrim((string) request()->getSchemeAndHttpHost(), '/');
        }

        if ($baseUrl === '') {
            return $relativeUrl;
        }

        return $baseUrl . (Str::startsWith($relativeUrl, '/') ? $relativeUrl : '/' . $relativeUrl);
    }

    private function storeAttachment(UploadedFile $file, string $uploaderUserId): array
    {
        $disk = config('filesystems.default', 'public');
        $folder = 'uploads/' . now()->format('Y/m/d');
        $safeName = preg_replace('/[^A-Za-z0-9\.\-_]/', '_', $file->getClientOriginalName());
        $storeName = (string) Str::uuid() . '_' . ($safeName ?: 'attachment');
        $path = $file->storeAs($folder, $storeName, $disk);

        $mime = $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream';

        $width = null;
        $height = null;
        $duration = null;

        if ($this->probe->isImageMime($mime)) {
            $dimensions = $this->probe->imageDimensions($file->getRealPath());
            $width = $dimensions['width'] ?? null;
            $height = $dimensions['height'] ?? null;
        } elseif ($this->probe->isVideoMime($mime)) {
            $metadata = $this->probe->videoMetadata($file->getRealPath());
            $width = $metadata['width'] ?? null;
            $height = $metadata['height'] ?? null;
            $duration = $metadata['duration'] ?? null;
        }

        $storedFile = FileModel::create([
            'uploader_user_id' => $uploaderUserId,
            's3_key' => $path,
            'mime_type' => $mime,
            'size_bytes' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'duration' => $duration,
        ]);

        $kind = $this->probe->isImageMime($mime)
            ? 'image'
            : ($this->probe->isVideoMime($mime) ? 'video' : 'file');

        return [
            'file_id' => (string) $storedFile->id,
            'kind' => $kind,
            'name' => $file->getClientOriginalName(),
            'mime' => $mime,
            'size' => (int) $storedFile->size_bytes,
            'url' => '/api/v1/files/' . $storedFile->id,
        ];
    }

    public function markRead(Request $request, string $id)
    {
        $authUser = $request->user();

        $chat = Chat::where('id', $id)
            ->where(function ($q) use ($authUser) {
                $q->where('user1_id', $authUser->id)
                    ->orWhere('user2_id', $authUser->id);
            })
            ->first();

        if (! $chat) {
            return $this->error('Chat not found', 404);
        }

        $readAt = now();

        $updated = Message::where('chat_id', $chat->id)
            ->where('sender_id', '!=', $authUser->id)
            ->where('is_read', false)
            ->whereNull('deleted_at')
            ->update([
                'is_read' => true,
                'updated_at' => $readAt,
            ]);

        broadcast(new ChatReadUpdated($chat, $authUser->id, $readAt))->toOthers();

        return $this->success([
            'updated_count' => $updated,
        ], 'Messages marked as read');
    }

    public function typing(Request $request, string $id)
    {
        $authUser = $request->user();
        $data = $request->validate([
            'is_typing' => ['required', 'boolean'],
        ]);

        $chat = Chat::where('id', $id)
            ->where(function ($q) use ($authUser) {
                $q->where('user1_id', $authUser->id)
                    ->orWhere('user2_id', $authUser->id);
            })
            ->first();

        if (! $chat) {
            return $this->error('Chat not found', 404);
        }

        broadcast(new ChatTyping((string) $chat->id, (string) $authUser->id, (bool) $data['is_typing']))->toOthers();

        return $this->success([
            'is_typing' => (bool) $data['is_typing'],
        ], 'Typing updated');
    }

    private function formatUserPayload(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'display_name' => $user->display_name
                ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'profile_photo_url' => $user->profile_photo_url,
        ];
    }

    // Testing notes:
    // - Start Reverb: php artisan reverb:start
    // - Send message as user A, listen as user B on private-chat.{chatId} => chat.message.sent
    // - Call typing endpoint => chat.typing
    // - Presence join/leave reflects online/offline in client
    // - Verify notifications row inserted for recipient user
}
