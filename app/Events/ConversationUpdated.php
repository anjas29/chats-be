<?php

namespace App\Events;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Conversation $conversation, public int $userId)
    {
        //
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['conversation' => $this->payload()];
    }

    /**
     * The conversation as the receiving user sees it (unread count, mute state).
     *
     * @return array<string, mixed>|null
     */
    private function payload(): ?array
    {
        $user = User::find($this->userId);

        $conversation = $user
            ? Conversation::query()->forUser($user)->whereKey($this->conversation->id)->first()
            : null;

        return $conversation ? ConversationResource::make($conversation)->resolve() : null;
    }
}
