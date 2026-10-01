<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attachment
 */
class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mime' => $this->mime,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'duration_ms' => $this->duration_ms,
            'url' => $this->url(),
            'thumbnail_url' => $this->thumbnailUrl(),
        ];
    }
}
