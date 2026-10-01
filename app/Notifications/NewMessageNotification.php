<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Message $message)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [FcmChannel::class];
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        $conversation = $this->message->conversation;

        $title = $conversation->isGroup()
            ? ($conversation->name ?? $this->message->sender->name)
            : $this->message->sender->name;

        $body = $conversation->isGroup()
            ? $this->message->sender->name.': '.$this->message->preview()
            : $this->message->preview();

        return (new FcmMessage(notification: new FcmNotification(title: $title, body: $body)))
            ->data([
                'conversation_id' => (string) $conversation->id,
                'message_id' => (string) $this->message->id,
            ])
            ->custom([
                'android' => ['priority' => 'high'],
                'apns' => ['headers' => ['apns-priority' => '10']],
            ]);
    }
}
