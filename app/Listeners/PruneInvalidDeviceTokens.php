<?php

namespace App\Listeners;

use App\Models\DeviceToken;
use Illuminate\Notifications\Events\NotificationFailed;
use Kreait\Firebase\Messaging\SendReport;
use NotificationChannels\Fcm\FcmChannel;

class PruneInvalidDeviceTokens
{
    /**
     * Delete tokens FCM reports as invalid or unregistered.
     */
    public function handle(NotificationFailed $event): void
    {
        if ($event->channel !== FcmChannel::class) {
            return;
        }

        $report = $event->data['report'] ?? null;

        if (! $report instanceof SendReport) {
            return;
        }

        if ($report->messageTargetWasInvalid() || $report->messageWasSentToUnknownToken()) {
            DeviceToken::where('token', $report->target()->value())->delete();
        }
    }
}
