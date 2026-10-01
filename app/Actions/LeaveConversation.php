<?php

namespace App\Actions;

use App\Enums\ParticipantRole;
use App\Events\ConversationUpdated;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeaveConversation
{
    /**
     * Remove a user from a conversation. When a group owner leaves, ownership
     * passes to the longest-standing remaining participant.
     */
    public function handle(Conversation $conversation, User $user): void
    {
        DB::transaction(function () use ($conversation, $user) {
            $participant = $conversation->participants()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if (! $participant) {
                return;
            }

            $wasOwner = $participant->role === ParticipantRole::Owner;

            $participant->update(['left_at' => now(), 'role' => ParticipantRole::Member]);

            if ($conversation->isGroup() && $wasOwner) {
                $conversation->participants()
                    ->whereNull('left_at')
                    ->orderByRaw("case when role = 'admin' then 0 else 1 end")
                    ->orderBy('joined_at')
                    ->first()
                    ?->update(['role' => ParticipantRole::Owner]);
            }
        });

        $conversation->participants()
            ->whereNull('left_at')
            ->pluck('user_id')
            ->each(fn (int $userId) => ConversationUpdated::dispatch($conversation, $userId));
    }
}
