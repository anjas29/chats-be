<?php

namespace App\Jobs;

use App\Models\Attachment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

class GenerateThumbnail implements ShouldQueue
{
    use Queueable;

    private const THUMBNAIL_WIDTH = 480;

    public function __construct(public Attachment $attachment)
    {
        //
    }

    /**
     * Record the image dimensions and store a JPEG thumbnail next to the original.
     * Formats the image driver cannot decode (e.g. HEIC) simply keep no thumbnail.
     */
    public function handle(): void
    {
        $disk = Storage::disk($this->attachment->disk);

        try {
            $image = Image::decodeBinary($disk->get($this->attachment->path));
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        $width = $image->width();
        $height = $image->height();

        $thumbnailPath = 'attachments/thumbs/'.$this->attachment->id.'.jpg';
        $disk->put($thumbnailPath, $image->scaleDown(width: self::THUMBNAIL_WIDTH)->encodeUsingFormat(Format::JPEG, quality: 80)->toString());

        $this->attachment->update([
            'width' => $width,
            'height' => $height,
            'thumbnail_path' => $thumbnailPath,
        ]);
    }
}
