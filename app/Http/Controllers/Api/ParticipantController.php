<?php

namespace App\Http\Controllers\Api;

use App\Actions\LeaveConversation;
use App\Enums\ParticipantRole;
use App\Events\ConversationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddParticipantsRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParticipantController extends Controller
{
    public function store(AddParticipantsRequest $request, Conversation $conversation): ConversationResource
    {
        $this->authorize('manageParticipants', $conversation);

        foreach ($request->input('user_ids') as $userId) {
            $conversation->participants()->updateOrCreate(
                ['user_id' => $userId],
                ['left_at' => null, 'joined_at' => now(), 'last_read_message_id' => $conversation->last_message_id, 'role' => ParticipantRole::Member],
            );
        }

        $conversation->participants()->whereNull('left_at')->pluck('user_id')
            ->each(fn (int $id) => ConversationUpdated::dispatch($conversation, $id));

        return ConversationResource::make(
            Conversation::query()->forUser($request->user())->findOrFail($conversation->id)
        );
    }

    public function destroy(Request $request, Conversation $conversation, User $user, LeaveConversation $leave): JsonResponse
    {
        if ($user->isNot($request->user())) {
            $this->authorize('manageParticipants', $conversation);

            abort_if(
                $conversation->participants()->where('user_id', $user->id)->where('role', ParticipantRole::Owner)->exists(),
                403,
                'The group owner cannot be removed.',
            );
        } else {
            $this->authorize('leave', $conversation);
        }

        $leave->handle($conversation, $user);
        ConversationUpdated::dispatch($conversation, $user->id);

        return response()->json(status: 204);
    }
}
