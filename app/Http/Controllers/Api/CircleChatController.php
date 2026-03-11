<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\CircleChat\MarkCircleChatMessagesReadRequest;
use App\Http\Requests\CircleChat\SendCircleChatMessageRequest;
use App\Http\Resources\CircleChatMessageResource;
use App\Models\Circle;
use App\Models\CircleChatMessage;
use App\Services\CircleChat\CircleChatService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CircleChatController extends BaseApiController
{
    public function __construct(
        private readonly CircleChatService $circleChatService,
    ) {
    }

    public function index(Request $request, Circle $circle)
    {
        try {
            $paginator = $this->circleChatService->getMessages(
                $request->user(),
                $circle,
                (int) $request->input('per_page', 20),
                $request->input('before_message_id')
            );

            return $this->success([
                'circle' => [
                    'id' => (string) $circle->id,
                    'name' => $circle->name,
                ],
                'messages' => CircleChatMessageResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ], 'Circle chat messages fetched successfully.');
        } catch (HttpException $exception) {
            return $this->error($exception->getMessage(), $exception->getStatusCode());
        }
    }

    public function store(SendCircleChatMessageRequest $request, Circle $circle)
    {
        try {
            $message = $this->circleChatService->sendMessage(
                $request->user(),
                $circle,
                $request->validated(),
                $request->file('attachment')
            );

            return $this->success(new CircleChatMessageResource($message), 'Message sent successfully.', 201);
        } catch (HttpException $exception) {
            return $this->error($exception->getMessage(), $exception->getStatusCode());
        }
    }

    public function markRead(MarkCircleChatMessagesReadRequest $request, Circle $circle)
    {
        try {
            $added = $this->circleChatService->markMessagesRead(
                $request->user(),
                $circle,
                $request->validated()['message_ids']
            );

            return $this->success([
                'read_count_added' => $added,
            ], 'Messages marked as read successfully.');
        } catch (HttpException $exception) {
            return $this->error($exception->getMessage(), $exception->getStatusCode());
        }
    }

    public function readDetails(Request $request, Circle $circle, CircleChatMessage $message)
    {
        try {
            $details = $this->circleChatService->getReadDetails($request->user(), $circle, $message);

            return $this->success($details, 'Message read details fetched successfully.');
        } catch (HttpException $exception) {
            return $this->error($exception->getMessage(), $exception->getStatusCode());
        }
    }

    public function deleteForMe(Request $request, Circle $circle, CircleChatMessage $message)
    {
        try {
            $this->circleChatService->deleteForMe($request->user(), $circle, $message);

            return $this->success(null, 'Message deleted for you successfully.');
        } catch (HttpException $exception) {
            return $this->error($exception->getMessage(), $exception->getStatusCode());
        }
    }

    public function destroy(Request $request, Circle $circle, CircleChatMessage $message)
    {
        try {
            $this->circleChatService->deleteForAll($request->user(), $circle, $message);

            return $this->success(null, 'Message deleted for all successfully.');
        } catch (HttpException $exception) {
            return $this->error($exception->getMessage(), $exception->getStatusCode());
        }
    }
}
