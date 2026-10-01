<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $deleted = $this->trashed();

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'client_uuid' => $this->client_uuid,
            'type' => $this->type,
            'body' => $deleted ? null : $this->body,
            'sender' => UserResource::make($this->whenLoaded('sender')),
            'sender_id' => $this->sender_id,
            'reply_to' => $this->when($this->reply_to_id !== null && $this->relationLoaded('replyTo'), fn () => [
                'id' => $this->replyTo->id,
                'sender_id' => $this->replyTo->sender_id,
                'type' => $this->replyTo->type,
                'preview' => $this->replyTo->trashed() ? null : $this->replyTo->preview(),
            ]),
            'attachments' => $deleted ? [] : AttachmentResource::collection($this->whenLoaded('attachments')),
            'is_deleted' => $deleted,
            'edited_at' => $this->edited_at,
            'created_at' => $this->created_at,
        ];
    }
}
