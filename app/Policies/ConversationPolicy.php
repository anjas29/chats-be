<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $this->participation($user, $conversation) !== null;
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $conversation->isGroup() && $this->participation($user, $conversation)?->canManage() === true;
    }

    public function manageParticipants(User $user, Conversation $conversation): bool
    {
        return $this->update($user, $conversation);
    }

    public function leave(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    private function participation(User $user, Conversation $conversation): ?ConversationParticipant
    {
        return $conversation->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();
    }
}
