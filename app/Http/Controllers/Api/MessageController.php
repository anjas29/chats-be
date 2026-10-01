<?php

namespace App\Http\Controllers\Api;

use App\Actions\SendMessage;
use App\Enums\MessageType;
use App\Events\MessageDeleted;
use App\Events\MessageUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMessageRequest;
use App\Http\Requests\Api\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    private const PAGE_SIZE = 50;

    /**
     * Newest first. Pass `before={oldest id you have}` to load older messages.
     * Deleted messages are returned as tombstones so clients can sync them.
     */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $limit = min(max($request->integer('limit', self::PAGE_SIZE), 1), 100);

        $messages = $conversation->messages()
            ->withTrashed()
            ->with(['sender', 'attachments', 'replyTo'])
            ->when($request->filled('before'), fn ($query) => $query->where('id', '<', $request->integer('before')))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return MessageResource::collection($messages)
            ->additional(['meta' => [
                'next_before' => $messages->count() === $limit ? $messages->last()->id : null,
            ]])
            ->response();
    }

    public function store(StoreMessageRequest $request, Conversation $conversation, SendMessage $send): JsonResponse
    {
        $this->authorize('sendMessage', $conversation);

        $message = $send->handle($conversation, $request->user(), $request->validated());

        return MessageResource::make($message->loadMissing(['sender', 'attachments', 'replyTo']))
            ->response()
            ->setStatusCode($message->wasRecentlyCreated ? 201 : 200);
    }

    public function update(UpdateMessageRequest $request, Message $message): MessageResource
    {
        $this->authorize('update', $message);
        $this->authorize('view', $message->conversation);

        abort_unless($message->type === MessageType::Text, 422, 'Only text messages can be edited.');

        $message->update(['body' => $request->input('body'), 'edited_at' => now()]);

        broadcast(new MessageUpdated($message))->toOthers();

        return MessageResource::make($message->load(['sender', 'attachments', 'replyTo']));
    }

    public function destroy(Message $message): JsonResponse
    {
        $this->authorize('delete', $message);
        $this->authorize('view', $message->conversation);

        $message->delete();

        broadcast(new MessageDeleted($message))->toOthers();

        return response()->json(status: 204);
    }
}
