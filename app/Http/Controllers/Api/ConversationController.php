<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateConversation;
use App\Actions\LeaveConversation;
use App\Enums\ConversationType;
use App\Events\ConversationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreConversationRequest;
use App\Http\Requests\Api\UpdateConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $conversations = Conversation::query()
            ->forUser($request->user())
            ->orderByDesc('conversations.updated_at')
            ->orderByDesc('conversations.id')
            ->cursorPaginate(30);

        return ConversationResource::collection($conversations);
    }

    public function store(StoreConversationRequest $request, CreateConversation $create): JsonResponse
    {
        $user = $request->user();

        $conversation = $request->enum('type', ConversationType::class) === ConversationType::Direct
            ? $create->direct($user, User::findOrFail($request->integer('user_id')))
            : $create->group($user, $request->input('name'), $request->input('member_ids'));

        return $this->respond($conversation, $user, $conversation->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        $this->authorize('view', $conversation);

        return ConversationResource::make($this->forViewer($conversation, $request->user()));
    }

    public function update(UpdateConversationRequest $request, Conversation $conversation): ConversationResource
    {
        $this->authorize('update', $conversation);

        $attributes = $request->safe()->only('name');

        if ($request->hasFile('avatar')) {
            $disk = config('chats.media_disk');
            $file = $request->file('avatar');

            $attributes['avatar_path'] = $file->storeAs('avatars', Str::ulid().'.'.$file->extension(), $disk);

            if ($conversation->avatar_path) {
                Storage::disk($disk)->delete($conversation->avatar_path);
            }
        }

        $conversation->update($attributes);

        $conversation->participants()->whereNull('left_at')->pluck('user_id')
            ->each(fn (int $userId) => ConversationUpdated::dispatch($conversation, $userId));

        return ConversationResource::make($this->forViewer($conversation, $request->user()));
    }

    /**
     * Leave the conversation (it disappears from the user's list; direct chats reappear on the next message).
     */
    public function destroy(Request $request, Conversation $conversation, LeaveConversation $leave): JsonResponse
    {
        $this->authorize('leave', $conversation);

        $leave->handle($conversation, $request->user());

        return response()->json(status: 204);
    }

    private function respond(Conversation $conversation, User $user, int $status): JsonResponse
    {
        return ConversationResource::make($this->forViewer($conversation, $user))
            ->response()
            ->setStatusCode($status);
    }

    private function forViewer(Conversation $conversation, User $user): Conversation
    {
        return Conversation::query()->forUser($user)->findOrFail($conversation->id);
    }
}
