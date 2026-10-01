<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class MediaUrl
{
    /**
     * Signed, short-lived URL for a file on the media disk. Falls back to a
     * plain URL on drivers that cannot sign (e.g. a local disk without serving).
     */
    public static function for(string $disk, string $path): string
    {
        $filesystem = Storage::disk($disk);

        if (method_exists($filesystem, 'providesTemporaryUrls') && $filesystem->providesTemporaryUrls()) {
            return $filesystem->temporaryUrl($path, now()->addMinutes(config('chats.media_url_ttl_minutes')));
        }

        return $filesystem->url($path);
    }
}
