<?php

namespace App\Actions;

use App\Enums\ConversationType;
use App\Enums\MessageType;
use App\Events\ConversationUpdated;
use App\Events\MessageSent;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SendMessage
{
    /**
     * Persist a message, then fan out realtime events and push notifications.
     *
     * Retrying with the same client_uuid returns the original message untouched.
     *
     * @param  array{type: string, body?: ?string, attachment_ids?: array<int, string>, reply_to_id?: ?int, client_uuid?: ?string}  $data
     */
    public function handle(Conversation $conversation, User $sender, array $data): Message
    {
        $clientUuid = $data['client_uuid'] ?? null;

        if ($clientUuid && ($existing = $this->findExisting($sender, $clientUuid))) {
            return $existing;
        }

        try {
            $message = DB::transaction(fn () => $this->store($conversation, $sender, $data));
        } catch (UniqueConstraintViolationException) {
            return $this->findExisting($sender, $clientUuid) ?? throw new \LogicException('Duplicate message not found.');
        }

        $this->dispatch($conversation, $sender, $message);

        return $message;
    }

    private function findExisting(User $sender, string $clientUuid): ?Message
    {
        return Message::query()
            ->where('sender_id', $sender->id)
            ->where('client_uuid', $clientUuid)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function store(Conversation $conversation, User $sender, array $data): Message
    {
        $attachmentIds = $data['attachment_ids'] ?? [];

        $attachments = Attachment::query()
            ->whereIn('id', $attachmentIds)
            ->where('user_id', $sender->id)
            ->whereNull('message_id')
            ->lockForUpdate()
            ->get();

        if ($attachments->count() !== count(array_unique($attachmentIds))) {
            throw ValidationException::withMessages([
                'attachment_ids' => 'One or more attachments are invalid or already used.',
            ]);
        }

        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'type' => MessageType::from($data['type']),
            'body' => $data['body'] ?? null,
            'reply_to_id' => $data['reply_to_id'] ?? null,
            'client_uuid' => $data['client_uuid'] ?? null,
        ]);

        Attachment::query()->whereIn('id', $attachments->modelKeys())->update(['message_id' => $message->id]);

        $conversation->forceFill(['last_message_id' => $message->id])->save();

        $conversation->participants()
            ->where('user_id', $sender->id)
            ->update(['last_read_message_id' => $message->id]);

        if ($conversation->type === ConversationType::Direct) {
            $conversation->participants()
                ->where('user_id', '!=', $sender->id)
                ->whereNotNull('left_at')
                ->update(['left_at' => null]);
        }

        return $message;
    }

    private function dispatch(Conversation $conversation, User $sender, Message $message): void
    {
        $message->load(['sender', 'attachments', 'replyTo']);

        broadcast(new MessageSent($message))->toOthers();

        $participants = $conversation->participants()
            ->whereNull('left_at')
            ->where('user_id', '!=', $sender->id)
            ->with('user.deviceTokens')
            ->get();

        foreach ($participants as $participant) {
            ConversationUpdated::dispatch($conversation, $participant->user_id);
        }

        $recipients = $participants
            ->reject(fn ($participant) => $participant->isMuted() || $participant->user->is_banned)
            ->map->user
            ->filter(fn (User $user) => $user->deviceTokens->isNotEmpty());

        Notification::send($recipients, new NewMessageNotification($message));
    }
}
