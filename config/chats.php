<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media disk
    |--------------------------------------------------------------------------
    |
    | Disk used for avatars and message attachments. Media is never public;
    | clients receive short-lived signed URLs.
    |
    */

    'media_disk' => env('MEDIA_DISK', 's3'),

    'media_url_ttl_minutes' => (int) env('MEDIA_URL_TTL_MINUTES', 60),

    'max_upload_kb' => 5120,

];
