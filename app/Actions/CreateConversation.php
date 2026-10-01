<?php

namespace App\Actions;

use App\Enums\ConversationType;
use App\Enums\ParticipantRole;
use App\Events\ConversationUpdated;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateConversation
{
    /**
     * Find-or-create the direct conversation between two users.
     */
    public function direct(User $creator, User $other): Conversation
    {
        $key = Conversation::directKeyFor($creator->id, $other->id);

        $conversation = DB::transaction(function () use ($creator, $other, $key) {
            $conversation = Conversation::firstOrCreate(
                ['direct_key' => $key],
                ['type' => ConversationType::Direct, 'created_by' => $creator->id],
            );

            foreach ([$creator, $other] as $user) {
                $conversation->participants()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['role' => ParticipantRole::Member, 'left_at' => null, 'joined_at' => now()],
                );
            }

            return $conversation;
        });

        if ($conversation->wasRecentlyCreated) {
            ConversationUpdated::dispatch($conversation, $other->id);
        }

        return $conversation;
    }

    /**
     * @param  array<int, int>  $memberIds
     */
    public function group(User $creator, string $name, array $memberIds): Conversation
    {
        $memberIds = collect($memberIds)->reject(fn (int $id) => $id === $creator->id)->unique();

        $conversation = DB::transaction(function () use ($creator, $name, $memberIds) {
            $conversation = Conversation::create([
                'type' => ConversationType::Group,
                'name' => $name,
                'created_by' => $creator->id,
            ]);

            $conversation->participants()->create([
                'user_id' => $creator->id,
                'role' => ParticipantRole::Owner,
                'joined_at' => now(),
            ]);

            foreach ($memberIds as $memberId) {
                $conversation->participants()->create([
                    'user_id' => $memberId,
                    'role' => ParticipantRole::Member,
                    'joined_at' => now(),
                ]);
            }

            return $conversation;
        });

        foreach ($memberIds as $memberId) {
            ConversationUpdated::dispatch($conversation, $memberId);
        }

        return $conversation;
    }
}
