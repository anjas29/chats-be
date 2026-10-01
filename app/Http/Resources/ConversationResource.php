<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'avatar_url' => $this->avatar_path
                ? MediaUrl::for(config('chats.media_disk'), $this->avatar_path)
                : null,
            'created_by' => $this->created_by,
            'participants' => $this->whenLoaded('participants', fn () => $this->participants->map(fn ($participant) => [
                'user' => UserResource::make($participant->user),
                'role' => $participant->role,
                'joined_at' => $participant->joined_at,
            ])),
            'last_message' => MessageResource::make($this->whenLoaded('lastMessage')),
            'unread_count' => (int) ($this->unread_count ?? 0),
            'my_role' => $this->viewer_role ?? null,
            'last_read_message_id' => $this->viewer_last_read_message_id ?? null,
            'muted_until' => $this->viewer_muted_until ?? null,
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
