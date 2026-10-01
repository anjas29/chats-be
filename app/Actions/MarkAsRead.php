<?php

namespace App\Actions;

use App\Events\MessageRead;
use App\Models\Conversation;
use App\Models\User;

class MarkAsRead
{
    /**
     * Move the user's read marker forward (never backwards) and notify others.
     */
    public function handle(Conversation $conversation, User $user, int $messageId): void
    {
        $updated = $conversation->participants()
            ->where('user_id', $user->id)
            ->where(fn ($query) => $query
                ->whereNull('last_read_message_id')
                ->orWhere('last_read_message_id', '<', $messageId))
            ->update(['last_read_message_id' => $messageId]);

        if ($updated) {
            broadcast(new MessageRead($conversation, $user, $messageId))->toOthers();
        }
    }
}
